<template>
    <v-card
        class="storage-audit-panel pa-4 pa-md-6"
        :class="{ 'storage-audit-panel--locked': isInteractionLocked }"
        rounded="xl"
        elevation="0">
        <div class="d-flex flex-wrap align-start justify-space-between ga-4 mb-4">
            <div>
                <div class="text-overline text-primary font-weight-bold">Lokale Materialdateien</div>
                <h2 class="text-h5 font-weight-bold">Aktive Schule aus Cloudflare lokal bereitstellen</h2>
                <div class="text-body-2 text-medium-emphasis">
                    Wenn du die Online-Datenbank lokal eingespielt hast, kannst du hier alle Materialdateien der aktiven Schule aus Cloudflare in den lokalen Speicher laden. Bereits vorhandene lokale Dateien bleiben unverändert.
                </div>
            </div>

            <div class="d-flex align-center ga-2">
                <v-btn
                    color="primary"
                    variant="tonal"
                    prepend-icon="mdi-refresh"
                    :loading="isLoading"
                    :disabled="isInteractionLocked"
                    @click="loadAudit">
                    Neu laden
                </v-btn>
            </div>
        </div>

        <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">
            {{ errorMessage }}
        </v-alert>

        <v-alert v-else-if="generatedAt" type="info" variant="tonal" class="mb-4">
            Stand: {{ formatReadableDateTime(generatedAt) }}
        </v-alert>

        <div v-if="isLoading" class="mb-4">
            <v-progress-linear
                color="primary"
                height="6"
                rounded
                :model-value="loadingProgressPercent" />
            <div class="text-caption text-medium-emphasis mt-2">
                Speicherprüfung läuft • {{ loadingProgressPercent }} %
                <span v-if="loadingProgressMessage"> • {{ loadingProgressMessage }}</span>
            </div>
        </div>

        <v-alert type="info" variant="tonal" class="mb-4">
            Geteilte oder verknüpfte Materialien sowie gelöschte, aber wiederherstellbare Materialien sind berücksichtigt. Beim Laden zählt jede Datei nur einmal, auch wenn sie in mehreren Karten vorkommt.
        </v-alert>

        <v-alert v-if="statusMessage" type="success" variant="tonal" class="mb-4">
            {{ statusMessage }}
        </v-alert>

        <v-skeleton-loader v-if="isLoading && !reports.length" type="article, article" />

        <v-row v-else dense>
            <v-col v-for="report in reports" :key="report.scope_key" cols="12" xl="6">
                <v-card class="storage-audit-scope pa-4 h-100" rounded="xl" variant="outlined">
                    <div class="d-flex flex-wrap align-start justify-space-between ga-3 mb-4">
                        <div>
                            <div class="text-overline text-primary font-weight-bold">{{ report.scope_label }}</div>
                            <div class="text-h6 font-weight-bold">
                                {{ report.school?.long_name || 'Alle Schulen' }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ report.bucket_prefix }}
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-end ga-2">
                            <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-database-search">
                                Cloudflare: {{ report.bucket.object_count }} Objekte
                            </v-chip>
                            <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-harddisk">
                                Cloudflare: {{ formatBytes(report.bucket.total_bytes) }}
                            </v-chip>
                        </div>
                    </div>

                    <div v-if="report.scope_key === 'active_school' && isLocalEnvironment" class="storage-audit-storage-compare mb-4">
                        <v-sheet class="storage-audit-storage-card pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">Lokal</div>
                            <div class="storage-audit-storage-card__path">{{ localStoragePath(report) }}</div>
                            <div class="storage-audit-kpi__value">{{ localFileCount(report) }} Dateien</div>
                            <div class="storage-audit-kpi__meta">{{ formatBytes(localStorageBytes(report)) }}</div>
                        </v-sheet>
                        <div class="storage-audit-storage-compare__middle">
                            <v-icon size="20">mdi-arrow-left-right</v-icon>
                        </div>
                        <v-sheet class="storage-audit-storage-card pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">Cloudflare</div>
                            <div class="storage-audit-storage-card__path">{{ cloudflareStoragePath(report) }}</div>
                            <div class="storage-audit-kpi__value">{{ cloudflareFileCount(report) }} Dateien</div>
                            <div class="storage-audit-kpi__meta">{{ formatBytes(cloudflareStorageBytes(report)) }}</div>
                        </v-sheet>
                    </div>

                    <v-alert
                        v-if="report.scope_key === 'active_school' && analysisText(report)"
                        type="info"
                        variant="tonal"
                        class="mb-4">
                        {{ analysisText(report) }}
                    </v-alert>

                    <div v-if="report.scope_key === 'active_school' && isLocalEnvironment" class="storage-audit-section-head">
                        <div>
                            <div class="text-subtitle-2 font-weight-bold">Alle Materialdateien dieser Schule lokal aktualisieren</div>
                            <div class="text-caption text-medium-emphasis">
                                Diese Dateien sind online vorhanden. Fehlende lokale Dateien werden automatisch ergänzt.
                            </div>
                        </div>
                        <div class="d-flex flex-column align-end ga-2">
                            <div class="text-caption text-medium-emphasis">
                                {{ report.cloud_sync_source.count }} Dateien
                            </div>
                            <v-btn
                                v-if="report.scope_key === 'active_school'"
                                color="primary"
                                variant="flat"
                                size="small"
                                prepend-icon="mdi-cloud-download-outline"
                                :disabled="report.cloud_sync_source.count === 0 || isSyncing || isInteractionLocked"
                                @click="syncLocalFiles(report)">
                                Alle Dateien lokal aktualisieren
                            </v-btn>
                        </div>
                    </div>
                    <v-alert v-if="report.scope_key === 'active_school' && report.cloud_sync_source.count === 0" type="success" variant="tonal" class="mb-4">
                        Für diese Schule wurden online keine Materialdateien gefunden.
                    </v-alert>

                    <template v-if="report.scope_key === 'active_school' && !isLocalEnvironment">
                        <div class="storage-audit-section-head">
                            <div>
                                <div class="text-subtitle-2 font-weight-bold">Cloudflare-Dateien ohne Materialeintrag</div>
                                <div class="text-caption text-medium-emphasis">
                                    Diese gespeicherten Dateien haben keinen passenden Materialanhang in der Datenbank.
                                </div>
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ report.differences.bucket_only.count }} Dateien
                            </div>
                        </div>

                        <v-list v-if="report.bucket_only_objects.length" class="bg-transparent pa-0 mb-4" density="compact">
                            <v-list-item
                                v-for="item in report.bucket_only_objects"
                                :key="`${report.scope_key}-bucket-only-${item.path}`"
                                class="px-0">
                                <template #title>
                                    <div class="storage-audit-path text-body-2">
                                        {{ item.path }}
                                    </div>
                                </template>
                                <template #subtitle>
                                    {{ formatBytes(item.size_bytes) }}
                                </template>
                            </v-list-item>

                            <v-list-item v-if="report.has_more_bucket_only_objects" class="px-0">
                                <template #title>
                                    Weitere gespeicherte Dateien ohne Materialeintrag wurden ausgeblendet.
                                </template>
                            </v-list-item>
                        </v-list>
                        <v-alert v-else type="success" variant="tonal" class="mb-4">
                            Keine gespeicherten Dateien ohne Materialeintrag gefunden.
                        </v-alert>
                    </template>

                    <template v-if="report.scope_key === 'all_schools'">
                        <div class="storage-audit-section-head">
                            <div>
                                <div class="text-subtitle-2 font-weight-bold">Cloudflare je Schule</div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ allSchoolsSummaryDescription }}
                                </div>
                            </div>
                        </div>

                        <v-list v-if="schoolCloudSummaries(report).length" class="bg-transparent pa-0" density="compact">
                            <v-list-item
                                v-for="summary in schoolCloudSummaries(report)"
                                :key="`cloud-school-${summary.school?.id || schoolLabel(summary)}`"
                                class="storage-audit-school-row px-0">
                                <template #title>
                                    <div class="text-body-2 font-weight-medium">
                                        {{ schoolLabel(summary) }}
                                    </div>
                                </template>
                                <template #subtitle>
                                    <div class="storage-audit-school-metrics">
                                        <span>{{ Number(summary.object_count || 0) }} Objekte</span>
                                        <span>{{ formatBytes(summary.total_bytes) }}</span>
                                        <span>{{ onlineMaterialsMissingFileCount(summary) }} Materialien mit fehlender Datei</span>
                                        <span>{{ onlineFilesWithoutMaterialCount(summary) }} Dateien ohne Material</span>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                        <v-alert v-else type="success" variant="tonal">
                            Keine Cloudflare-Dateien für Schulen gefunden.
                        </v-alert>
                    </template>

                    <template v-if="report.scope_key === 'active_school'">
                        <div class="storage-audit-section-head">
                            <div>
                                <div class="text-subtitle-2 font-weight-bold">Materialien mit fehlender Datei</div>
                                <div class="text-caption text-medium-emphasis">
                                    Diese Materialeinträge haben einen Anhang, dessen Datei in Cloudflare nicht existiert.
                                </div>
                            </div>
                            <div class="d-flex flex-column align-end ga-2">
                                <div class="text-caption text-medium-emphasis">
                                    {{ report.differences.database_only.count }} Einträge
                                </div>
                                <v-btn
                                    v-if="report.database_only_attachments.length"
                                    color="error"
                                    variant="tonal"
                                    size="small"
                                    prepend-icon="mdi-delete"
                                    :disabled="isInteractionLocked"
                                    @click="openDatabaseOnlyMaterialsDeleteDialog(report)">
                                    Alle Materialien löschen
                                </v-btn>
                            </div>
                        </div>

                        <v-alert v-if="report.differences.database_only.count > 0" type="warning" variant="tonal" class="mb-4">
                            Diese Aktion löscht die betroffenen Materialeinträge endgültig.
                        </v-alert>

                        <v-list v-if="report.database_only_attachments.length" class="bg-transparent pa-0" density="compact">
                            <v-list-item
                                v-for="item in report.database_only_attachments"
                                :key="`${report.scope_key}-database-only-${item.id}`"
                                class="px-0">
                                <template #title>
                                    <div class="storage-audit-row-action">
                                        <div class="text-body-2 font-weight-medium">
                                            {{ formatDatabaseOnlyAttachmentLabel(report, item) }}
                                        </div>
                                        <v-btn
                                            aria-label="Material löschen"
                                            title="Material löschen"
                                            color="error"
                                            variant="text"
                                            size="small"
                                            :disabled="isInteractionLocked"
                                            @click="openBrokenAttachmentDeleteDialog(report, item)">
                                            X
                                        </v-btn>
                                    </div>
                                </template>
                                <template #subtitle>
                                    <span v-if="item.deleted_at"> • gelöscht {{ formatReadableDateTime(item.deleted_at) }}</span>
                                    <span> • {{ formatBytes(item.size_bytes) }}</span>
                                </template>
                            </v-list-item>

                            <v-list-item v-if="report.has_more_database_only_attachments" class="px-0">
                                <template #title>
                                    Weitere DB-Einträge wurden ausgeblendet.
                                </template>
                            </v-list-item>
                        </v-list>
                        <v-alert v-else type="success" variant="tonal">
                            Keine Einträge gefunden, die auch online fehlen.
                        </v-alert>
                    </template>
                </v-card>
            </v-col>
        </v-row>
    </v-card>

    <v-dialog v-model="isSyncDialogOpen" persistent max-width="620">
        <v-card class="pa-4 pa-md-6" rounded="xl">
            <div class="text-overline text-primary font-weight-bold">Materialdateien lokal aktualisieren</div>
            <div class="text-subtitle-1 font-weight-bold mb-2">
                Download läuft im Hintergrund
            </div>
            <div class="text-body-2 text-medium-emphasis mb-4">
                {{ syncDialogMessage }}
            </div>
            <v-progress-linear
                color="primary"
                height="8"
                rounded
                :model-value="syncProgressPercent" />
            <div class="text-caption text-medium-emphasis mt-2">
                {{ syncProgressPercent }} %
                <span v-if="syncProgressMessage"> • {{ syncProgressMessage }}</span>
            </div>
        </v-card>
    </v-dialog>

    <v-dialog v-model="isBrokenAttachmentDeleteDialogOpen" persistent max-width="620">
        <v-card class="pa-4 pa-md-6" rounded="xl">
            <div class="text-overline text-error font-weight-bold">Material löschen</div>
            <div class="text-subtitle-1 font-weight-bold mb-2">
                {{ brokenAttachmentDeleteDialogTitle() }}
            </div>
            <div class="text-body-2 text-medium-emphasis mb-4">
                {{ brokenAttachmentDeleteDialogMessage() }}
            </div>
            <v-alert type="warning" variant="tonal" class="mb-4">
                Diese Aktion löscht die betroffenen Materialeinträge endgültig. Sie landen nicht im Papierkorb.
            </v-alert>
            <div class="d-flex justify-end ga-2">
                <v-btn variant="text" :disabled="isDeletingBrokenAttachment" @click="closeBrokenAttachmentDeleteDialog">
                    Abbrechen
                </v-btn>
                <v-btn
                    color="error"
                    variant="flat"
                    prepend-icon="mdi-delete"
                    :loading="isDeletingBrokenAttachment"
                    :disabled="!brokenAttachmentDeleteDialog || isDeletingBrokenAttachment"
                    @click="confirmBrokenAttachmentDelete">
                    Material löschen
                </v-btn>
            </div>
        </v-card>
    </v-dialog>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export default {
    data() {
        return {
            reports: [],
            generatedAt: '',
            isLoading: false,
            loadingProgressPercent: 0,
            loadingProgressMessage: '',
            auditOperationId: '',
            auditPollTimeoutId: null,
            syncOperationId: '',
            syncPollTimeoutId: null,
            syncProgressPercent: 0,
            syncProgressMessage: '',
            syncDialogMessage: '',
            isSyncDialogOpen: false,
            errorMessage: '',
            statusMessage: '',
            isLocalEnvironment: false,
            isSyncing: false,
            isDeletingBrokenAttachment: false,
            isBrokenAttachmentDeleteDialogOpen: false,
            brokenAttachmentDeleteDialog: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        selectedSchoolId() {
            return Number(this.config?.selected_school?.id || 0)
        },
        isInteractionLocked() {
            return this.isLoading || this.isDeletingBrokenAttachment
        },
        allSchoolsSummaryDescription() {
            if (this.isLocalEnvironment) {
                return 'Pro Schule: gespeicherte Objekte und belegter Cloudflare-Speicher.'
            }

            return 'Pro Schule: gespeicherte Objekte, belegter Speicher und fehlende Zuordnungen.'
        },
    },

    watch: {
        selectedSchoolId: {
            immediate: true,
            handler() {
                this.loadAudit()
            },
        },
    },

    methods: {
        resetAuditProgress() {
            this.loadingProgressPercent = 0
            this.loadingProgressMessage = ''
            this.auditOperationId = ''
        },
        clearAuditPolling() {
            if (this.auditPollTimeoutId) {
                clearTimeout(this.auditPollTimeoutId)
                this.auditPollTimeoutId = null
            }
        },
        resetSyncState() {
            this.syncOperationId = ''
            this.syncProgressPercent = 0
            this.syncProgressMessage = ''
            this.syncDialogMessage = ''
            this.isSyncDialogOpen = false
        },
        clearSyncPolling() {
            if (this.syncPollTimeoutId) {
                clearTimeout(this.syncPollTimeoutId)
                this.syncPollTimeoutId = null
            }
        },
        applyAuditOperationState(operation) {
            this.loadingProgressPercent = Number(operation?.progress || 0)
            this.loadingProgressMessage = String(operation?.message || '')
        },
        applySyncOperationState(operation) {
            this.syncProgressPercent = Number(operation?.progress || 0)
            this.syncProgressMessage = String(operation?.message || '')
        },
        scheduleAuditPolling(operationId, refreshAfterSeconds = 1) {
            this.clearAuditPolling()

            this.auditPollTimeoutId = window.setTimeout(() => {
                this.pollAuditStatus(operationId)
            }, Math.max(1, Number(refreshAfterSeconds || 1)) * 1000)
        },
        scheduleSyncPolling(operationId, refreshAfterSeconds = 1) {
            this.clearSyncPolling()

            this.syncPollTimeoutId = window.setTimeout(() => {
                this.pollSyncStatus(operationId)
            }, Math.max(1, Number(refreshAfterSeconds || 1)) * 1000)
        },
        async pollAuditStatus(operationId) {
            try {
                const response = await axios.get(`/api/admin/materials/storage-audit/operations/${operationId}`)
                const operation = response.data?.data || null
                if (!operation) {
                    throw new Error('Der Prüfstatus ist leer.')
                }

                this.auditOperationId = String(operation.operation_id || operationId)
                this.applyAuditOperationState(operation)

                if (operation.status === 'completed') {
                    this.reports = operation?.result?.reports || []
                    this.generatedAt = operation?.result?.generated_at || ''
                    this.isLocalEnvironment = Boolean(operation?.result?.is_local_environment || false)
                    this.isLoading = false
                    this.clearAuditPolling()

                    return
                }

                if (operation.status === 'failed') {
                    this.errorMessage = operation?.message || 'Speicherprüfung konnte nicht geladen werden.'
                    this.isLoading = false
                    this.clearAuditPolling()

                    return
                }

                this.scheduleAuditPolling(this.auditOperationId, operation?.refresh_after_seconds || 1)
            } catch (error) {
                this.errorMessage = error?.response?.data?.message || 'Speicherprüfung konnte nicht geladen werden.'
                this.isLoading = false
                this.clearAuditPolling()
            }
        },
        async pollSyncStatus(operationId) {
            try {
                const response = await axios.get(`/api/admin/materials/storage-audit/sync-operations/${operationId}`)
                const operation = response.data?.data || null
                if (!operation) {
                    throw new Error('Der Download-Status ist leer.')
                }

                this.syncOperationId = String(operation.operation_id || operationId)
                this.applySyncOperationState(operation)

                if (operation.status === 'completed') {
                    this.clearSyncPolling()
                    await this.loadAudit()
                    this.isSyncing = false
                    this.isSyncDialogOpen = false

                    useNotificationStore().notify({
                        message: operation?.message || 'Der Download der Materialdateien ist abgeschlossen.',
                        type: 'success',
                    })

                    return
                }

                if (operation.status === 'failed') {
                    this.errorMessage = operation?.message || 'Die Materialdateien konnten nicht lokal geladen werden.'
                    this.isSyncing = false
                    this.isSyncDialogOpen = false
                    this.clearSyncPolling()

                    useNotificationStore().notify({
                        message: this.errorMessage,
                        type: 'error',
                    })

                    return
                }

                this.scheduleSyncPolling(this.syncOperationId, operation?.refresh_after_seconds || 1)
            } catch (error) {
                this.errorMessage = error?.response?.data?.message || 'Die Materialdateien der aktiven Schule konnten nicht lokal geladen werden.'
                this.isSyncing = false
                this.isSyncDialogOpen = false
                this.clearSyncPolling()

                useNotificationStore().notify({
                    message: this.errorMessage,
                    type: 'error',
                })
            }
        },
        async loadAudit() {
            if (!this.config?.is_auth) {
                return
            }

            if (this.isLoading) {
                return
            }

            this.clearAuditPolling()
            this.isLoading = true
            this.resetAuditProgress()
            this.errorMessage = ''
            this.statusMessage = ''

            try {
                const payload = {}
                if (this.selectedSchoolId > 0) {
                    payload.school_id = this.selectedSchoolId
                }

                const response = await axios.post('/api/admin/materials/storage-audit/start', payload)
                const operation = response.data?.data || null
                if (!operation?.operation_id) {
                    throw new Error('Die Speicherprüfung konnte nicht gestartet werden.')
                }

                this.auditOperationId = String(operation.operation_id)
                this.applyAuditOperationState(operation)
                await this.pollAuditStatus(this.auditOperationId)
            } catch (error) {
                this.reports = []
                this.generatedAt = ''
                this.isLocalEnvironment = false
                this.errorMessage = error?.response?.data?.message || 'Speicherprüfung konnte nicht geladen werden.'
                this.isLoading = false
            }
        },
        openBrokenAttachmentDeleteDialog(report, item) {
            if (this.isInteractionLocked) {
                return
            }

            const attachmentId = Number(item?.id || 0)
            if (attachmentId <= 0) {
                return
            }

            this.brokenAttachmentDeleteDialog = {
                attachment_id: attachmentId,
                scope_key: String(report?.scope_key || ''),
                school_id: Number(report?.school?.id || 0) || null,
                label: this.formatDatabaseOnlyAttachmentLabel(report, item),
                is_bulk: false,
            }
            this.isBrokenAttachmentDeleteDialogOpen = true
        },
        openDatabaseOnlyMaterialsDeleteDialog(report) {
            if (this.isInteractionLocked) {
                return
            }

            const count = Array.isArray(report?.database_only_attachments)
                ? report.database_only_attachments.length
                : 0
            if (count <= 0) {
                return
            }

            this.brokenAttachmentDeleteDialog = {
                attachment_id: null,
                scope_key: String(report?.scope_key || ''),
                school_id: Number(report?.school?.id || 0) || null,
                label: `${count} Materialien mit fehlender Datei`,
                count,
                is_bulk: true,
            }
            this.isBrokenAttachmentDeleteDialogOpen = true
        },
        closeBrokenAttachmentDeleteDialog() {
            if (this.isDeletingBrokenAttachment) {
                return
            }

            this.isBrokenAttachmentDeleteDialogOpen = false
            this.brokenAttachmentDeleteDialog = null
        },
        async confirmBrokenAttachmentDelete() {
            if (!this.brokenAttachmentDeleteDialog) {
                return
            }

            this.isDeletingBrokenAttachment = true
            this.errorMessage = ''

            try {
                const payload = {
                    scope_key: this.brokenAttachmentDeleteDialog.scope_key,
                }

                if (this.brokenAttachmentDeleteDialog.school_id) {
                    payload.school_id = this.brokenAttachmentDeleteDialog.school_id
                }

                const endpoint = this.brokenAttachmentDeleteDialog.is_bulk
                    ? '/api/admin/materials/storage-audit/database-only-materials'
                    : `/api/admin/materials/storage-audit/database-only-attachments/${this.brokenAttachmentDeleteDialog.attachment_id}`

                const response = await axios.delete(endpoint, { data: payload })

                await this.loadAudit()
                this.statusMessage = response.data?.message || 'Das Material mit fehlender Datei wurde endgültig gelöscht.'
                this.isBrokenAttachmentDeleteDialogOpen = false
                this.brokenAttachmentDeleteDialog = null
            } catch (error) {
                this.errorMessage = error?.response?.data?.message || 'Das Material konnte nicht gelöscht werden.'
            } finally {
                this.isDeletingBrokenAttachment = false
            }
        },
        async syncLocalFiles(report) {
            const cloudSyncCount = Number(report?.cloud_sync_source?.count || 0)
            if (report?.scope_key !== 'active_school' || cloudSyncCount <= 0 || this.isSyncing || this.isInteractionLocked) {
                return
            }

            this.isSyncing = true
            this.errorMessage = ''
            this.statusMessage = ''
            this.clearSyncPolling()
            this.resetSyncState()

            try {
                const payload = {
                    scope_key: report.scope_key,
                }

                if (report?.school?.id) {
                    payload.school_id = report.school.id
                }

                const response = await axios.post('/api/admin/materials/storage-audit/sync-local', payload)
                const operation = response.data?.data || null
                if (!operation?.operation_id) {
                    throw new Error('Der Download konnte nicht gestartet werden.')
                }

                this.syncOperationId = String(operation.operation_id)
                this.syncDialogMessage = response.data?.message || 'Der Download der Materialdateien wurde im Hintergrund gestartet.'
                this.isSyncDialogOpen = true
                this.applySyncOperationState(operation)
                await this.pollSyncStatus(this.syncOperationId)
            } catch (error) {
                this.errorMessage = error?.response?.data?.message || 'Die Materialdateien der aktiven Schule konnten nicht lokal geladen werden.'
                this.isSyncing = false
                this.clearSyncPolling()
                this.resetSyncState()
            } finally {
            }
        },
        formatBytes(bytes) {
            const value = Number(bytes || 0)
            if (value <= 0) {
                return '0 B'
            }

            const units = ['B', 'KB', 'MB', 'GB', 'TB']
            const exponent = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1)
            const size = value / (1024 ** exponent)
            const digits = exponent === 0 ? 0 : size >= 10 ? 1 : 2

            return `${size.toFixed(digits)} ${units[exponent]}`
        },
        formatSignedBytes(bytes) {
            const value = Number(bytes || 0)
            if (value === 0) {
                return '0 B'
            }

            return `${value > 0 ? '+' : '-'}${this.formatBytes(Math.abs(value))}`
        },
        formatPercent(part, total) {
            const totalValue = Number(total || 0)
            if (totalValue <= 0) {
                return '0 %'
            }

            const percentage = (Number(part || 0) / totalValue) * 100

            return `${percentage.toFixed(1)} %`
        },
        formatReadableDateTime(value) {
            if (!value) {
                return ''
            }

            const date = new Date(value)
            if (Number.isNaN(date.getTime())) {
                return String(value)
            }

            const formatter = new Intl.DateTimeFormat('de-DE', {
                timeZone: 'Europe/Vienna',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23',
            })

            const parts = Object.fromEntries(formatter.formatToParts(date).map((part) => [part.type, part.value]))
            const day = parts.day || '??'
            const month = parts.month || '??'
            const year = parts.year || '????'
            const hour = parts.hour || '??'
            const minute = parts.minute || '??'

            return `${day}.${month}.${year}, ${hour}:${minute} Uhr`
        },
        formatDatabaseOnlyAttachmentLabel(report, item) {
            const parts = [
                item?.subject_name,
                item?.topic_name,
                item?.unit_name,
                item?.material_card_title || 'Material',
            ]
                .map((value) => String(value || '').trim())
                .filter((value) => value.length > 0)

            const baseLabel = parts.join(' - ') || 'Material'
            const schoolId = this.databaseOnlyAttachmentSchoolId(report, item)

            return schoolId > 0 ? `${baseLabel} • Schule ${schoolId}` : baseLabel
        },
        databaseOnlyAttachmentSchoolId(report, item) {
            const directSchoolId = Number(item?.school_id || report?.school?.id || 0)
            if (directSchoolId > 0) {
                return directSchoolId
            }

            const filePath = String(item?.file_path || '').trim()
            const schoolMatch = filePath.match(/materials\/schools\/(\d+)\//)

            return schoolMatch ? Number(schoolMatch[1] || 0) : 0
        },
        databaseTotalBytes(report) {
            return Number(report?.database?.all?.total_bytes || 0)
        },
        bucketVsDatabaseTotalBytes(report) {
            return Number(report?.bucket?.total_bytes || 0) - this.databaseTotalBytes(report)
        },
        localFileCount(report) {
            return Number(report?.local?.file_count || 0)
        },
        localStorageBytes(report) {
            return Number(report?.local?.total_bytes || 0)
        },
        localStoragePath(report) {
            return String(report?.local?.path || '').trim()
        },
        cloudflareFileCount(report) {
            return Number(report?.bucket?.object_count || 0)
        },
        cloudflareStorageBytes(report) {
            return Number(report?.bucket?.total_bytes || 0)
        },
        cloudflareStoragePath(report) {
            return String(report?.bucket?.path || report?.bucket_prefix || '').trim()
        },
        schoolCloudSummaries(report) {
            const summaries = Array.isArray(report?.school_cloud_summaries) ? report.school_cloud_summaries : []
            if (summaries.length > 0 || report?.scope_key !== 'all_schools') {
                return summaries
            }

            const activeSchoolReport = this.reports.find((candidate) => candidate?.scope_key === 'active_school')
            if (!activeSchoolReport?.school) {
                return []
            }

            return [
                {
                    school: activeSchoolReport.school,
                    object_count: Number(activeSchoolReport?.bucket?.object_count || 0),
                    total_bytes: Number(activeSchoolReport?.bucket?.total_bytes || 0),
                    materials_missing_file_count: Number(activeSchoolReport?.differences?.database_only?.count || 0),
                    files_without_material_count: Number(activeSchoolReport?.differences?.bucket_only?.count || 0),
                },
            ]
        },
        schoolLabel(summary) {
            const school = summary?.school || {}
            const name = String(school.long_name || school.short_name || '').trim()
            const id = Number(school.id || 0)

            return name || (id > 0 ? `Schule ${id}` : 'Unbekannte Schule')
        },
        onlineMaterialsMissingFileCount(summary) {
            if (this.isLocalEnvironment) {
                return 0
            }

            return Number(summary?.materials_missing_file_count || 0)
        },
        onlineFilesWithoutMaterialCount(summary) {
            if (this.isLocalEnvironment) {
                return 0
            }

            return Number(summary?.files_without_material_count || 0)
        },
        brokenAttachmentDeleteDialogTitle() {
            if (!this.brokenAttachmentDeleteDialog) {
                return 'Material löschen'
            }

            if (this.brokenAttachmentDeleteDialog.is_bulk) {
                return 'Alle Materialien löschen?'
            }

            return this.brokenAttachmentDeleteDialog.label
        },
        brokenAttachmentDeleteDialogMessage() {
            if (!this.brokenAttachmentDeleteDialog) {
                return ''
            }

            if (this.brokenAttachmentDeleteDialog.is_bulk) {
                return `${this.brokenAttachmentDeleteDialog.count} Materialeinträge mit fehlender Datei werden gelöscht.`
            }

            return `Der Materialeintrag "${this.brokenAttachmentDeleteDialog.label}" wird gelöscht.`
        },
        analysisText(report) {
            if (!report) {
                return ''
            }

            const cloudSyncCount = Number(report?.cloud_sync_source?.count || 0)
            const localMissingCount = Number(report?.differences?.local_missing?.count || 0)
            const databaseOnlyCount = Number(report?.differences?.database_only?.count || 0)
            const trashedCount = Number(report?.database?.trashed?.count || 0)

            if (report?.scope_key === 'active_school' && this.isLocalEnvironment && cloudSyncCount > 0) {
                return `Mit einem Klick startest du den Hintergrund-Download für ${cloudSyncCount} Materialdateien der aktiven Schule. ${trashedCount > 0 ? `Gelöschte, aber wiederherstellbare Materialien sind mit berücksichtigt (${trashedCount}).` : ''}`.trim()
            }

            if (localMissingCount > 0) {
                return `${localMissingCount} Dateien fehlen noch lokal.`
            }

            if (databaseOnlyCount > 0) {
                return `${databaseOnlyCount} Materialeinträge müssen manuell geprüft werden, weil die Datei auch online fehlt.`
            }

            return 'Für diesen Bereich ist aktuell nichts zu tun.'
        },
    },
    beforeUnmount() {
        this.clearAuditPolling()
        this.clearSyncPolling()
    },
}
</script>

<style scoped>
.storage-audit-panel {
    width: 100%;
}

.storage-audit-panel--locked {
    cursor: progress;
}

.storage-audit-panel--locked :deep(button),
.storage-audit-panel--locked :deep(a),
.storage-audit-panel--locked :deep([role='button']) {
    pointer-events: none;
}

.storage-audit-storage-compare {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: stretch;
    gap: 10px;
}

.storage-audit-storage-card {
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.1);
}

.storage-audit-storage-card__path {
    min-height: 34px;
    margin-top: 6px;
    color: rgba(15, 23, 42, 0.7);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.storage-audit-storage-compare__middle {
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(15, 23, 42, 0.55);
}

@media (max-width: 600px) {
    .storage-audit-storage-compare {
        grid-template-columns: minmax(0, 1fr);
    }

    .storage-audit-storage-compare__middle {
        display: none;
    }
}

.storage-audit-path {
    overflow-wrap: anywhere;
}

.storage-audit-school-row + .storage-audit-school-row {
    border-top: 1px solid rgba(15, 23, 42, 0.08);
}

.storage-audit-school-metrics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-top: 4px;
    color: rgba(15, 23, 42, 0.68);
    font-size: 12px;
}

.storage-audit-row-action {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 8px;
}

@media (max-width: 960px) {
    .storage-audit-school-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .storage-audit-school-metrics {
        grid-template-columns: minmax(0, 1fr);
    }
}

.storage-audit-kpi {
    background:
        linear-gradient(180deg, rgba(15, 23, 42, 0.025), rgba(15, 23, 42, 0.01)),
        #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.storage-audit-kpi__label {
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(15, 23, 42, 0.62);
}

.storage-audit-kpi__value {
    margin-top: 6px;
    font-size: 18px;
    font-weight: 700;
    color: rgba(15, 23, 42, 0.96);
}

.storage-audit-kpi__meta {
    margin-top: 4px;
    font-size: 12px;
    color: rgba(15, 23, 42, 0.68);
}

.storage-audit-section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.storage-audit-scope {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.98));
    border-color: rgba(15, 23, 42, 0.08);
    color: rgba(15, 23, 42, 0.95);
}

.storage-audit-scope :deep(.v-alert) {
    color: rgba(15, 23, 42, 0.92);
}

.storage-audit-scope :deep(.text-medium-emphasis) {
    color: rgba(15, 23, 42, 0.7) !important;
}

.storage-audit-scope :deep(.text-caption) {
    color: rgba(15, 23, 42, 0.68);
}
</style>
