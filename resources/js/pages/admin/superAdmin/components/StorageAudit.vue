<template>
    <v-card class="storage-audit-panel pa-4 pa-md-6" rounded="xl" elevation="0">
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
                    :disabled="isLoading"
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
                                {{ report.bucket.object_count }} Objekte
                            </v-chip>
                            <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-harddisk">
                                Belegt {{ formatBytes(report.bucket.total_bytes) }}
                            </v-chip>
                        </div>
                    </div>

                    <div class="storage-audit-summary-head mb-3">
                        <div>
                            <div class="text-subtitle-2 font-weight-bold">Was ist hier zu tun?</div>
                            <div class="text-caption text-medium-emphasis">
                                Zwei sichere Fälle: remote nach lokal laden oder remote manuell gegenprüfen
                            </div>
                        </div>
                    </div>

                    <div class="storage-audit-kpi-grid mb-4">
                        <v-sheet class="storage-audit-kpi pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">Lokal fehlt noch</div>
                            <div class="storage-audit-kpi__value">{{ report.differences.local_missing.count }}</div>
                            <div class="storage-audit-kpi__meta">{{ formatBytes(report.differences.local_missing.total_bytes) }}</div>
                        </v-sheet>
                        <v-sheet class="storage-audit-kpi pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">Fehlt auch online</div>
                            <div class="storage-audit-kpi__value">{{ report.differences.database_only.count }}</div>
                            <div class="storage-audit-kpi__meta">{{ formatBytes(report.differences.database_only.total_bytes) }}</div>
                        </v-sheet>
                        <v-sheet class="storage-audit-kpi pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">Nur online ohne Materialeintrag</div>
                            <div class="storage-audit-kpi__value">{{ report.differences.bucket_only.count }}</div>
                            <div class="storage-audit-kpi__meta">{{ formatBytes(report.differences.bucket_only.total_bytes) }}</div>
                        </v-sheet>
                    </div>

                    <v-alert
                        v-if="analysisText(report)"
                        :type="report.differences.bucket_only.total_bytes > 0 ? 'warning' : 'info'"
                        variant="tonal"
                        class="mb-4">
                        {{ analysisText(report) }}
                    </v-alert>

                    <div v-if="report.scope_key === 'active_school'" class="storage-audit-section-head">
                        <div>
                            <div class="text-subtitle-2 font-weight-bold">Alle Materialdateien dieser Schule lokal laden</div>
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
                                :disabled="report.cloud_sync_source.count === 0 || isSyncing"
                                @click="syncLocalFiles(report)">
                                Alle Dateien lokal laden
                            </v-btn>
                        </div>
                    </div>
                    <v-alert v-if="report.scope_key === 'active_school' && report.cloud_sync_source.count === 0" type="success" variant="tonal" class="mb-4">
                        Für diese Schule wurden online keine Materialdateien gefunden.
                    </v-alert>

                    <div class="storage-audit-section-head">
                        <div>
                            <div class="text-subtitle-2 font-weight-bold">Nur online, ohne Materialeintrag</div>
                            <div class="text-caption text-medium-emphasis">
                                Diese Dateien liegen noch in Cloudflare, haben aber lokal keinen passenden Materialeintrag
                            </div>
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ report.differences.bucket_only.count }} Dateien
                        </div>
                    </div>

                    <v-alert v-if="report.differences.bucket_only.count > 0" type="warning" variant="tonal" class="mb-4">
                        Remote-Löschungen sind hier deaktiviert. Diese Liste dient nur zur Prüfung gegen die Remote-Daten.
                    </v-alert>

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
                                Weitere Bucket-Reste wurden ausgeblendet.
                            </template>
                        </v-list-item>
                    </v-list>
                    <v-alert v-else type="success" variant="tonal" class="mb-4">
                        Keine verwaisten Dateien gefunden.
                    </v-alert>

                    <div class="storage-audit-section-head">
                        <div>
                            <div class="text-subtitle-2 font-weight-bold">Fehlt auch online</div>
                            <div class="text-caption text-medium-emphasis">
                                Diese Materialeinträge zeigen auf eine Datei, die weder lokal noch in Cloudflare gefunden wurde
                            </div>
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ report.differences.database_only.count }} Einträge
                        </div>
                    </div>

                    <v-alert v-if="report.differences.database_only.count > 0" type="warning" variant="tonal" class="mb-4">
                        Löschungen sind im Audit deaktiviert. Bitte Eintrag und Datei direkt gegen die Remote-Daten prüfen.
                    </v-alert>

                    <v-list v-if="report.database_only_attachments.length" class="bg-transparent pa-0" density="compact">
                        <v-list-item
                            v-for="item in report.database_only_attachments"
                            :key="`${report.scope_key}-database-only-${item.id}`"
                            class="px-0">
                            <template #title>
                                <div class="text-body-2 font-weight-medium">
                                    {{ formatDatabaseOnlyAttachmentLabel(report, item) }}
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
                </v-card>
            </v-col>
        </v-row>
    </v-card>

    <v-dialog v-model="isSyncDialogOpen" persistent max-width="620">
        <v-card class="pa-4 pa-md-6" rounded="xl">
            <div class="text-overline text-primary font-weight-bold">Materialdateien lokal laden</div>
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

    <v-dialog v-model="isPurgeDialogOpen" persistent max-width="620">
        <v-card class="pa-4 pa-md-6" rounded="xl">
            <div class="text-overline text-primary font-weight-bold">Verwaiste Dateien löschen</div>
            <div class="text-subtitle-1 font-weight-bold mb-2">
                {{ purgeDialogTitle() }}
            </div>
            <div class="text-body-2 text-medium-emphasis mb-4">
                {{ purgeDialogMessage() }}
            </div>
            <v-alert type="warning" variant="tonal" class="mb-4">
                Dieser Vorgang löscht die Dateien endgültig aus dem Bucket. Die zugehörigen Datenbankzeilen wurden bereits gelöscht.
            </v-alert>
            <div class="d-flex justify-end ga-2">
                <v-btn variant="text" :disabled="isPurging" @click="closePurgeDialog">
                    Abbrechen
                </v-btn>
                <v-btn
                    color="error"
                    variant="flat"
                    prepend-icon="mdi-delete-sweep"
                    :loading="isPurging"
                    :disabled="!purgeDialogReport"
                    @click="confirmPurge">
                    Jetzt löschen
                </v-btn>
            </div>
        </v-card>
    </v-dialog>

    <v-dialog v-model="isBrokenAttachmentDeleteDialogOpen" persistent max-width="620">
        <v-card class="pa-4 pa-md-6" rounded="xl">
            <div class="text-overline text-error font-weight-bold">Defekten Anhang löschen</div>
            <div class="text-subtitle-1 font-weight-bold mb-2">
                {{ brokenAttachmentDeleteDialogTitle() }}
            </div>
            <div class="text-body-2 text-medium-emphasis mb-4">
                {{ brokenAttachmentDeleteDialogMessage() }}
            </div>
            <v-alert type="warning" variant="tonal" class="mb-4">
                Die Datei ist bereits lokal und online nicht mehr vorhanden. Es wird nur der defekte Anhang aus der Materialkarte entfernt.
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
                    :disabled="!brokenAttachmentDeleteDialog"
                    @click="confirmBrokenAttachmentDelete">
                    Anhang löschen
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
            isPurging: false,
            isSyncing: false,
            isPurgeDialogOpen: false,
            purgeDialogReport: null,
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
                this.errorMessage = error?.response?.data?.message || 'Speicherprüfung konnte nicht geladen werden.'
                this.isLoading = false
            }
        },
        openPurgeDialog(report) {
            const bucketOnlyCount = Number(report?.differences?.bucket_only?.count || 0)
            if (bucketOnlyCount <= 0) {
                return
            }

            this.purgeDialogReport = {
                scope_key: report.scope_key,
                scope_label: report.scope_label,
                school_id: report?.school?.id || null,
                school_label: report?.school?.long_name || 'Alle Schulen',
                count: bucketOnlyCount,
                bytes: Number(report?.differences?.bucket_only?.total_bytes || 0),
            }
            this.isPurgeDialogOpen = true
        },
        closePurgeDialog() {
            if (this.isPurging) {
                return
            }

            this.isPurgeDialogOpen = false
            this.purgeDialogReport = null
        },
        openBrokenAttachmentDeleteDialog(report, item) {
            const attachmentId = Number(item?.id || 0)
            if (attachmentId <= 0) {
                return
            }

            this.brokenAttachmentDeleteDialog = {
                attachment_id: attachmentId,
                scope_key: String(report?.scope_key || ''),
                school_id: Number(report?.school?.id || 0) || null,
                label: this.formatDatabaseOnlyAttachmentLabel(report, item),
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
        async confirmPurge() {
            if (!this.purgeDialogReport) {
                return
            }

            this.isPurging = true
            this.errorMessage = ''

            try {
                const payload = {
                    scope_key: this.purgeDialogReport.scope_key,
                }

                if (this.purgeDialogReport.school_id) {
                    payload.school_id = this.purgeDialogReport.school_id
                }

                const response = await axios.post('/api/admin/materials/storage-audit/purge', payload)
                await this.loadAudit()
                this.isPurging = false
                this.statusMessage = response.data?.message || 'Verwaiste Dateien wurden gelöscht.'
                this.closePurgeDialog()
            } catch (error) {
                this.errorMessage = error?.response?.data?.message || 'Verwaiste Dateien konnten nicht gelöscht werden.'
            } finally {
                this.isPurging = false
            }
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

                const response = await axios.delete(
                    `/api/admin/materials/storage-audit/database-only-attachments/${this.brokenAttachmentDeleteDialog.attachment_id}`,
                    { data: payload },
                )

                await this.loadAudit()
                this.statusMessage = response.data?.message || 'Der defekte Anhang wurde entfernt.'
                this.closeBrokenAttachmentDeleteDialog()
            } catch (error) {
                this.errorMessage = error?.response?.data?.message || 'Der defekte Anhang konnte nicht gelöscht werden.'
            } finally {
                this.isDeletingBrokenAttachment = false
            }
        },
        async syncLocalFiles(report) {
            const cloudSyncCount = Number(report?.cloud_sync_source?.count || 0)
            if (report?.scope_key !== 'active_school' || cloudSyncCount <= 0 || this.isSyncing) {
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
        purgeDialogTitle() {
            if (!this.purgeDialogReport) {
                return 'Verwaiste Dateien löschen'
            }

            return `${this.purgeDialogReport.scope_label}: ${this.purgeDialogReport.school_label}`
        },
        brokenAttachmentDeleteDialogTitle() {
            if (!this.brokenAttachmentDeleteDialog) {
                return 'Defekten Anhang löschen'
            }

            return this.brokenAttachmentDeleteDialog.label
        },
        brokenAttachmentDeleteDialogMessage() {
            if (!this.brokenAttachmentDeleteDialog) {
                return ''
            }

            return `Der Eintrag "${this.brokenAttachmentDeleteDialog.label}" wird aus der Materialkarte entfernt.`
        },
        purgeDialogMessage() {
            if (!this.purgeDialogReport) {
                return ''
            }

            const countLabel = this.purgeDialogReport.count === 1 ? 'Datei' : 'Dateien'
            return `${this.purgeDialogReport.count} ${countLabel} ohne Materialeintrag mit insgesamt ${this.formatBytes(this.purgeDialogReport.bytes)} werden dauerhaft aus dem Bucket entfernt.`
        },
        analysisText(report) {
            if (!report) {
                return ''
            }

            const cloudSyncCount = Number(report?.cloud_sync_source?.count || 0)
            const localMissingCount = Number(report?.differences?.local_missing?.count || 0)
            const databaseOnlyCount = Number(report?.differences?.database_only?.count || 0)
            const bucketOnlyCount = Number(report?.differences?.bucket_only?.count || 0)
            const trashedCount = Number(report?.database?.trashed?.count || 0)

            if (report?.scope_key === 'active_school' && cloudSyncCount > 0) {
                return `Mit einem Klick startest du den Hintergrund-Download für ${cloudSyncCount} Materialdateien der aktiven Schule. ${trashedCount > 0 ? `Gelöschte, aber wiederherstellbare Materialien sind mit berücksichtigt (${trashedCount}).` : ''}`.trim()
            }

            if (localMissingCount > 0) {
                return `${localMissingCount} Dateien fehlen noch lokal.`
            }

            if (databaseOnlyCount > 0) {
                return `${databaseOnlyCount} Materialeinträge müssen manuell geprüft werden, weil die Datei auch online fehlt.`
            }

            if (bucketOnlyCount > 0) {
                return `${bucketOnlyCount} Dateien liegen nur noch online und müssen direkt gegen die Remote-Daten geprüft werden.`
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

.storage-audit-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

@media (min-width: 960px) {
    .storage-audit-kpi-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
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

.storage-audit-summary-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    color: rgba(15, 23, 42, 0.9);
}

.storage-audit-path {
    word-break: break-word;
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
