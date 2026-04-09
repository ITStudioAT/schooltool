<template>
    <v-card class="storage-audit-panel pa-4 pa-md-6" rounded="xl" elevation="0">
        <div class="d-flex flex-wrap align-start justify-space-between ga-4 mb-4">
            <div>
                <div class="text-overline text-primary font-weight-bold">Speicherprüfung</div>
                <h2 class="text-h5 font-weight-bold">Cloudflare vs. Datenbank</h2>
                <div class="text-body-2 text-medium-emphasis">
                    Vergleich der aktiven Schule und aller Schulen zusammen. Bucket-Reste sind Objekte ohne Datenbankzeile.
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

                        <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-database-search">
                            {{ report.bucket.object_count }} Objekte
                        </v-chip>
                    </div>

                    <div class="storage-audit-summary-head mb-3">
                        <div>
                            <div class="text-subtitle-2 font-weight-bold">Zusätzliche Kennzahlen</div>
                            <div class="text-caption text-medium-emphasis">
                                Einordnung von DB-Volumen, Bucket-Volumen und Restanteilen
                            </div>
                        </div>
                    </div>

                    <div class="storage-audit-kpi-grid mb-4">
                        <v-sheet class="storage-audit-kpi pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">Bucket gesamt</div>
                            <div class="storage-audit-kpi__value">{{ formatBytes(report.bucket.total_bytes) }}</div>
                        </v-sheet>
                        <v-sheet class="storage-audit-kpi pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">DB live</div>
                            <div class="storage-audit-kpi__value">{{ formatBytes(report.database.live.total_bytes) }}</div>
                        </v-sheet>
                        <v-sheet class="storage-audit-kpi pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">DB gelöscht</div>
                            <div class="storage-audit-kpi__value">{{ formatBytes(report.database.trashed.total_bytes) }}</div>
                        </v-sheet>
                        <v-sheet class="storage-audit-kpi pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">Bucket-Reste</div>
                            <div class="storage-audit-kpi__value">{{ formatBytes(report.differences.bucket_only.total_bytes) }}</div>
                        </v-sheet>
                        <v-sheet class="storage-audit-kpi pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">DB gesamt</div>
                            <div class="storage-audit-kpi__value">{{ formatBytes(databaseTotalBytes(report)) }}</div>
                        </v-sheet>
                        <v-sheet class="storage-audit-kpi pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">Bucket vs. DB gesamt</div>
                            <div class="storage-audit-kpi__value">{{ formatSignedBytes(bucketVsDatabaseTotalBytes(report)) }}</div>
                        </v-sheet>
                        <v-sheet class="storage-audit-kpi pa-3" rounded="lg">
                            <div class="storage-audit-kpi__label">Bucket-Reste Anteil</div>
                            <div class="storage-audit-kpi__value">{{ formatPercent(report.differences.bucket_only.total_bytes, report.bucket.total_bytes) }}</div>
                        </v-sheet>
                    </div>

                    <v-alert
                        v-if="analysisText(report)"
                        :type="report.differences.bucket_only.total_bytes > 0 ? 'warning' : 'info'"
                        variant="tonal"
                        class="mb-4">
                        {{ analysisText(report) }}
                    </v-alert>

                    <div class="storage-audit-section-head">
                        <div>
                            <div class="text-subtitle-2 font-weight-bold">Verwaiste Dateien</div>
                            <div class="text-caption text-medium-emphasis">
                                Objekte im Bucket, für die keine Datenbankzeile existiert
                            </div>
                        </div>
                        <div class="d-flex flex-column align-end ga-2">
                            <div class="text-caption text-medium-emphasis">
                                {{ report.differences.bucket_only.count }} Einträge
                            </div>
                            <v-btn
                                color="error"
                                variant="tonal"
                                size="small"
                                prepend-icon="mdi-delete-sweep"
                                :disabled="report.differences.bucket_only.count === 0 || isPurging"
                                @click="openPurgeDialog(report)">
                                Alle verwaisten Dateien löschen
                            </v-btn>
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
                                Weitere Bucket-Reste wurden ausgeblendet.
                            </template>
                        </v-list-item>
                    </v-list>
                    <v-alert v-else type="success" variant="tonal" class="mb-4">
                        Keine verwaisten Dateien gefunden.
                    </v-alert>

                    <div class="storage-audit-section-head">
                        <div>
                            <div class="text-subtitle-2 font-weight-bold">DB ohne Bucket-Datei</div>
                            <div class="text-caption text-medium-emphasis">
                                Datenbankzeilen, deren Datei im Bucket fehlt
                            </div>
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ report.differences.database_only.count }} Einträge
                        </div>
                    </div>

                    <v-list v-if="report.database_only_attachments.length" class="bg-transparent pa-0" density="compact">
                        <v-list-item
                            v-for="item in report.database_only_attachments"
                            :key="`${report.scope_key}-database-only-${item.id}`"
                            class="px-0">
                            <template #title>
                                <div class="storage-audit-path text-body-2">
                                    {{ item.file_path }}
                                </div>
                            </template>
                            <template #subtitle>
                                <span>{{ item.material_card_title || 'Material' }}</span>
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
                        Keine fehlenden Dateien in der Datenbank gefunden.
                    </v-alert>
                </v-card>
            </v-col>
        </v-row>
    </v-card>

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
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    data() {
        return {
            reports: [],
            generatedAt: '',
            isLoading: false,
            errorMessage: '',
            statusMessage: '',
            isPurging: false,
            isPurgeDialogOpen: false,
            purgeDialogReport: null,
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
        async loadAudit() {
            if (!this.config?.is_auth) {
                return
            }

            this.isLoading = true
            this.errorMessage = ''
            this.statusMessage = ''

            try {
                const params = {}
                if (this.selectedSchoolId > 0) {
                    params.school_id = this.selectedSchoolId
                }

                const response = await axios.get('/api/admin/materials/storage-audit', { params })
                this.reports = response.data?.data?.reports || []
                this.generatedAt = response.data?.data?.generated_at || ''
            } catch (error) {
                this.reports = []
                this.generatedAt = ''
                this.errorMessage = error?.response?.data?.message || 'Speicherprüfung konnte nicht geladen werden.'
            } finally {
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
        purgeDialogMessage() {
            if (!this.purgeDialogReport) {
                return ''
            }

            const countLabel = this.purgeDialogReport.count === 1 ? 'Datei' : 'Dateien'
            return `${this.purgeDialogReport.count} verwaiste ${countLabel} mit insgesamt ${this.formatBytes(this.purgeDialogReport.bytes)} werden dauerhaft aus dem Bucket entfernt.`
        },
        analysisText(report) {
            if (!report) {
                return ''
            }

            const bucketOnlyBytes = Number(report?.differences?.bucket_only?.total_bytes || 0)
            const trashedBytes = Number(report?.database?.trashed?.total_bytes || 0)
            const databaseOnlyBytes = Number(report?.differences?.database_only?.total_bytes || 0)

            if (bucketOnlyBytes > 0) {
                return `Gelöschte Anhänge belegen noch ${this.formatBytes(trashedBytes)}. Zusätzlich liegen ${this.formatBytes(bucketOnlyBytes)} verwaiste Dateien ohne Datenbankzeile vor.`
            }

            if (trashedBytes > 0) {
                return `Gelöschte Anhänge belegen noch ${this.formatBytes(trashedBytes)} bis der Purge sie entfernt.`
            }

            if (databaseOnlyBytes > 0) {
                return `Es gibt ${this.formatBytes(databaseOnlyBytes)} Datenbankeinträge ohne Bucket-Datei.`
            }

            return 'Bucket und Datenbank stimmen auf dieser Ebene überein.'
        },
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
