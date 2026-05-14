<template>
    <v-col cols="12" md="6" lg="7" xl="4">
        <v-card rounded="lg" border class="mb-4">
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-upload" />
                TXT-Datei importieren
            </v-card-title>
            <v-card-text>
                <v-alert type="info" variant="tonal" class="mb-3">
                    <template v-if="semester2Start">
                        Semester 2 beginnt am <strong>{{ semester2Start }}</strong>
                    </template>
                    <template v-else>
                        Kein Startdatum für Semester 2 hinterlegt.
                    </template>
                    <a href="#" class="ml-1" @click.prevent="openSchoolyearEdit">
                        Schuljahr ändern
                    </a>
                </v-alert>
                <v-alert v-if="uploadError" type="error" variant="tonal" class="mb-3">
                    {{ uploadError }}
                </v-alert>
                <FileUpload
                    v-if="semester2StartRaw"
                    :path="'/api/admin/students-timetables/upload'"
                    fileLabel
                    :allowedFileTypes="['text/plain']"
                    @fileUploadFinished="onUploadFinished"
                    @error="onUploadError" />
            </v-card-text>
        </v-card>

        <v-card rounded="lg" border>
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-file-document-multiple-outline" />
                Stundenplan-Importe
                <v-chip v-if="schoolyearName" size="small" variant="tonal" color="light-blue">
                    {{ schoolyearName }}
                </v-chip>
            </v-card-title>
            <v-card-text>
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-2" />

                <v-alert v-if="!loading && !imports.length && !mainDatasetEntriesCount" type="info" variant="tonal">
                    Noch keine Datei für dieses Schuljahr importiert.
                </v-alert>

                <template v-if="mainDataset || mainImport">
                    <section class="main-import-summary">
                        <div class="d-flex align-center ga-2 mb-3">
                            <v-icon icon="mdi-database-outline" color="primary" />
                            <div class="flex-grow-1">
                                <div class="text-caption text-medium-emphasis">Hauptdatenbestand</div>
                                <div class="font-weight-bold">{{ mainDataset?.name || 'Aktiver Stundenplan' }}</div>
                            </div>
                            <v-chip size="small" color="primary" variant="tonal">
                                {{ mainDataset?.table || 'student_timetable_entries' }}
                            </v-chip>
                        </div>

                        <div class="import-meta-grid main-import-meta-grid">
                            <div class="import-meta-item">
                                <span>Zeitraum</span>
                                <strong>{{ datasetDateRangeLabel(mainDataset) }}</strong>
                            </div>
                            <div class="import-meta-item">
                                <span>Stundenplan-Einträge</span>
                                <strong>{{ mainDatasetEntriesCount }}</strong>
                            </div>
                            <div class="import-meta-item">
                                <span>Verschiedene Kurse</span>
                                <strong>{{ mainDataset?.courses_count || 0 }}</strong>
                            </div>
                            <div class="import-meta-item">
                                <span>Zuletzt geändert</span>
                                <strong>{{ formatDate(mainDataset?.updated_at) || '-' }}</strong>
                            </div>
                        </div>

                        <v-expansion-panels
                            v-if="activeDatasetCourses.length"
                            variant="accordion"
                            class="main-dataset-course-panels mt-3">
                            <v-expansion-panel elevation="0" class="main-dataset-course-panel">
                                <v-expansion-panel-title>
                                    <div class="main-dataset-course-title">
                                        <v-icon icon="mdi-book-open-variant-outline" color="primary" size="18" />
                                        <span class="font-weight-medium">Alle Kurse</span>
                                        <v-chip size="x-small" color="primary" variant="tonal">
                                            {{ activeDatasetCourses.length }}
                                        </v-chip>
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <v-table density="compact">
                                        <thead>
                                            <tr>
                                                <th>Kurs</th>
                                                <th class="text-right">Einträge</th>
                                                <th>Zeitraum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="courseItem in activeDatasetCourses" :key="courseItem.name">
                                                <td class="font-weight-medium">{{ courseItem.name }}</td>
                                                <td class="text-right">{{ courseItem.entries_count }}</td>
                                                <td>{{ datasetCourseDateRangeLabel(courseItem) }}</td>
                                            </tr>
                                        </tbody>
                                    </v-table>
                                </v-expansion-panel-text>
                            </v-expansion-panel>
                        </v-expansion-panels>

                        <v-alert v-if="importIsProcessing(mainImport)" type="info" variant="tonal" class="mt-3 mb-0">
                            <div class="mb-2">{{ importStatusText(mainImport) }}</div>
                            <v-progress-linear
                                :model-value="importProgress(mainImport)"
                                :indeterminate="!mainImport.progress_total"
                                color="primary" />
                        </v-alert>
                    </section>

                    <div class="d-flex align-center ga-2 mt-5 mb-2">
                        <v-icon icon="mdi-history" size="18" color="primary" />
                        <span class="text-subtitle-2 font-weight-bold">Importverlauf</span>
                        <v-chip size="x-small" variant="tonal" color="primary">{{ imports.length }}</v-chip>
                    </div>

                    <v-expansion-panels variant="accordion" multiple>
                        <v-expansion-panel
                            v-for="importItem in imports"
                            :key="importItem.id"
                            elevation="0"
                            class="import-history-panel">
                            <v-expansion-panel-title>
                                <div class="import-history-title">
                                    <v-icon icon="mdi-file-document-outline" color="primary" size="18" />
                                    <div class="flex-grow-1">
                                        <div class="font-weight-medium">{{ importItem.original_filename }}</div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ formatDate(importItem.imported_at) }} · {{ dateRangeLabel(importItem) }}
                                        </div>
                                    </div>
                                    <v-chip size="x-small" :color="statusColor(importItem)" variant="tonal">
                                        {{ statusText(importItem) }}
                                    </v-chip>
                                    <v-btn
                                        prepend-icon="mdi-database-remove-outline"
                                        size="small"
                                        variant="text"
                                        color="error"
                                        class="import-history-unimport-button"
                                        :disabled="importIsProcessing(importItem)"
                                        @click.stop="openDeleteDialog(importItem)">
                                        Löschen
                                    </v-btn>
                                </div>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <div class="import-meta-grid mb-3">
                                    <div class="import-meta-item">
                                        <span>Dateiname</span>
                                        <strong>{{ importItem.original_filename }}</strong>
                                    </div>
                                    <div class="import-meta-item">
                                        <span>Gesamtzeilen</span>
                                        <strong>{{ importItem.total_lines || 0 }}</strong>
                                    </div>
                                    <div class="import-meta-item">
                                        <span>Stundenplan-Einträge</span>
                                        <strong>{{ importItem.sections?.TT || 0 }}</strong>
                                    </div>
                                    <div class="import-meta-item">
                                        <span>Verschiedene Kurse</span>
                                        <strong>{{ importItem.tt_courses || 0 }}</strong>
                                    </div>
                                    <div class="import-meta-item">
                                        <span>Zeitraum</span>
                                        <strong>{{ dateRangeLabel(importItem) }}</strong>
                                    </div>
                                    <div class="import-meta-item">
                                        <span>Importiert</span>
                                        <strong>{{ formatDate(importItem.imported_at) }}</strong>
                                    </div>
                                </div>

                                <v-alert v-if="importIsProcessing(importItem)" type="info" variant="tonal" class="mb-3">
                                    <div class="mb-2">{{ importStatusText(importItem) }}</div>
                                    <v-progress-linear
                                        :model-value="importProgress(importItem)"
                                        :indeterminate="!importItem.progress_total"
                                        color="primary" />
                                </v-alert>

                                <v-alert v-if="importItem.import_status === 'failed'" type="error" variant="tonal" class="mb-3">
                                    {{ importItem.import_message || 'Import fehlgeschlagen.' }}
                                </v-alert>

                                <v-table v-if="importItem.import_status === 'completed'" density="comfortable">
                                    <thead>
                                        <tr>
                                            <th>Sektion</th>
                                            <th>Beschreibung</th>
                                            <th class="text-right">Anzahl</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="(count, code) in importItem.sections" :key="`${importItem.id}-${code}`">
                                            <tr>
                                                <td>
                                                    <v-chip size="small" color="primary" variant="tonal">{{ code }}</v-chip>
                                                </td>
                                                <td>{{ sectionLabel(code) }}</td>
                                                <td class="text-right font-weight-medium">{{ count }}</td>
                                            </tr>
                                            <tr v-if="code === 'TT' && importItem.tt_courses" class="tt-sub-row">
                                                <td></td>
                                                <td class="text-caption pl-6">davon verschiedene Kurse</td>
                                                <td class="text-right font-weight-medium text-caption">{{ importItem.tt_courses }}</td>
                                            </tr>
                                            <tr v-if="code === 'TT' && importItem.tt_first_date" class="tt-sub-row">
                                                <td></td>
                                                <td class="text-caption pl-6">Zeitraum</td>
                                                <td class="text-right font-weight-medium text-caption">
                                                    {{ dateRangeLabel(importItem) }}
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="2" class="font-weight-bold">Gesamt</td>
                                            <td class="text-right font-weight-bold">{{ importItem.total_lines }}</td>
                                        </tr>
                                    </tfoot>
                                </v-table>

                                <div class="d-flex justify-end mt-3">
                                    <v-btn
                                        prepend-icon="mdi-database-remove-outline"
                                        size="small"
                                        variant="text"
                                        color="error"
                                        :disabled="importIsProcessing(importItem)"
                                        @click="openDeleteDialog(importItem)">
                                        Löschen
                                    </v-btn>
                                </div>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </template>
            </v-card-text>
        </v-card>

        <v-dialog v-model="schoolyearDialog" max-width="500" persistent>
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-calendar-edit" />
                    Schuljahr ändern
                </v-card-title>
                <v-card-text>
                    <v-form ref="schoolyearForm" v-model="schoolyearValid" @submit.prevent="saveSchoolyear">
                        <v-text-field
                            v-model="schoolyearData.name"
                            label="Bezeichnung"
                            :rules="[required(), maxLength(255)]"
                            variant="outlined"
                            density="comfortable"
                            class="mb-2" />
                        <v-text-field
                            v-model="schoolyearData.from"
                            label="Beginn des Schuljahres (jjjj-mm-tt)"
                            :rules="[dateOrNull()]"
                            variant="outlined"
                            density="comfortable"
                            class="mb-2" />
                        <v-text-field
                            v-model="schoolyearData.until"
                            label="Ende des Schuljahres (jjjj-mm-tt)"
                            :rules="[dateOrNull()]"
                            variant="outlined"
                            density="comfortable"
                            class="mb-2" />
                        <v-text-field
                            v-model="schoolyearData.sem_2_start"
                            label="Beginn des 2. Semesters (jjjj-mm-tt)"
                            :rules="[dateOrNull()]"
                            variant="outlined"
                            density="comfortable" />
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="savingSchoolyear" @click="schoolyearDialog = false">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" :loading="savingSchoolyear" @click="saveSchoolyear">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="440" persistent>
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-delete-alert-outline" color="error" />
                    Import löschen
                </v-card-title>
                <v-card-text>
                    Soll der Importlauf
                    <strong>{{ deleteTargetImport?.original_filename }}</strong>
                    gelöscht werden? Dabei werden die diesem Importlauf zugeordneten Stundenplan-Einträge aus
                    <strong>student_timetable_entries</strong>
                    entfernt und der aktive Stundenplan wird aus den übrigen Importläufen neu aufgebaut.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="deleting" @click="closeDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="deleting" @click="deleteImport">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useValidationRulesSetup } from '@/helpers/rules'
import FileUpload from '@/pages/components/FileUpload.vue'

const SECTION_LABELS = {
    VV: 'Kopfdaten / Version',
    SU: 'Unterrichtsfächer',
    TE: 'Lehrer',
    RM: 'Räume',
    KL: 'Klassen',
    GR: 'Gruppen',
    LS: 'Unterrichtseinheiten',
    TT: 'Stundenplan-Einträge',
}

export default {
    name: 'StudentsTimetablesImport',
    setup() {
        return useValidationRulesSetup()
    },
    components: { FileUpload },
    data() {
        return {
            imports: [],
            mainDataset: null,
            deleteTargetImport: null,
            loading: false,
            deleteDialog: false,
            deleting: false,
            schoolyearDialog: false,
            schoolyearData: {},
            schoolyearValid: false,
            savingSchoolyear: false,
            uploadError: '',
            pollingInterval: null,
        }
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        schoolyearName() {
            return this.config?.selected_schoolyear?.name || ''
        },
        mainImport() {
            return this.imports[0] || null
        },
        mainDatasetEntriesCount() {
            return Number(this.mainDataset?.entries_count || 0)
        },
        activeDatasetCourses() {
            return Array.isArray(this.mainDataset?.courses) ? this.mainDataset.courses : []
        },
        semester2StartRaw() {
            return this.config?.selected_schoolyear?.sem_2_start || ''
        },
        semester2Start() {
            const date = this.semester2StartRaw
            if (!date) return ''
            const d = new Date(date)
            return d.toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
    },
    watch: {
        'config.selected_schoolyear.id'() {
            this.loadImport()
        },
    },
    mounted() {
        this.loadImport()
    },
    unmounted() {
        this.clearPolling()
    },
    methods: {
        async loadImport() {
            this.loading = true
            this.uploadError = ''
            try {
                const response = await axios.get('/api/admin/students-timetables/imports', {
                    params: { page: 1, per_page: 100 },
                })
                this.imports = response.data.data || []
                this.mainDataset = response.data.main_dataset || null
                this.updatePolling()
            } catch {
                this.imports = []
                this.mainDataset = null
                this.clearPolling()
            } finally {
                this.loading = false
            }
        },
        onUploadFinished() {
            this.uploadError = ''
            this.loadImport()
            this.schedulePolling()
        },
        onUploadError() {
            this.uploadError = 'Der Import konnte nicht durchgeführt werden. Bitte prüfen Sie die TXT-Datei und das Semester-2-Startdatum.'
        },
        openSchoolyearEdit() {
            const sy = this.config?.selected_schoolyear
            if (!sy) return
            this.schoolyearData = { ...sy }
            this.schoolyearDialog = true
        },
        async saveSchoolyear() {
            this.schoolyearValid = false
            await this.$refs.schoolyearForm.validate()
            if (!this.schoolyearValid) return

            this.savingSchoolyear = true
            try {
                const store = useSchoolyearStore()
                if (await store.update(this.schoolyearData)) {
                    const adminStore = useAdminStore()
                    await adminStore.loadConfig()
                    this.schoolyearDialog = false
                }
            } finally {
                this.savingSchoolyear = false
            }
        },
        openDeleteDialog(importItem) {
            this.deleteTargetImport = importItem
            this.deleteDialog = true
        },
        closeDeleteDialog() {
            this.deleteDialog = false
            this.deleteTargetImport = null
        },
        async deleteImport() {
            if (!this.deleteTargetImport) return

            this.deleting = true
            try {
                await axios.delete(`/api/admin/students-timetables/imports/${this.deleteTargetImport.id}`)
                this.deleteTargetImport = null
                this.deleteDialog = false
                await this.loadImport()
            } finally {
                this.deleting = false
            }
        },
        updatePolling() {
            if (this.imports.some((importItem) => this.importIsProcessing(importItem))) {
                this.schedulePolling()

                return
            }

            this.clearPolling()
        },
        schedulePolling() {
            if (this.pollingInterval) return

            this.pollingInterval = window.setInterval(() => {
                this.loadImport()
            }, 2000)
        },
        clearPolling() {
            if (!this.pollingInterval) return

            window.clearInterval(this.pollingInterval)
            this.pollingInterval = null
        },
        sectionLabel(code) {
            return SECTION_LABELS[code] || code
        },
        dateRangeLabel(importItem) {
            if (!importItem?.tt_first_date) return 'Kein Zeitraum'

            if (!importItem.tt_last_date || importItem.tt_first_date === importItem.tt_last_date) {
                return importItem.tt_first_date
            }

            return `${importItem.tt_first_date} - ${importItem.tt_last_date}`
        },
        datasetDateRangeLabel(dataset) {
            if (!dataset?.first_date) return 'Kein Zeitraum'

            if (!dataset.last_date || dataset.first_date === dataset.last_date) {
                return dataset.first_date
            }

            return `${dataset.first_date} - ${dataset.last_date}`
        },
        datasetCourseDateRangeLabel(courseItem) {
            if (!courseItem?.first_date) return 'Kein Zeitraum'

            if (!courseItem.last_date || courseItem.first_date === courseItem.last_date) {
                return courseItem.first_date
            }

            return `${courseItem.first_date} - ${courseItem.last_date}`
        },
        importIsProcessing(importItem) {
            return ['pending', 'running', 'deleting'].includes(importItem?.import_status)
        },
        importProgress(importItem) {
            const current = Number(importItem?.progress_current || 0)
            const total = Number(importItem?.progress_total || 0)
            if (!total) return 0

            return Math.min(100, Math.round((current / total) * 100))
        },
        importStatusText(importItem) {
            return importItem?.import_message || 'Import wird verarbeitet.'
        },
        statusText(importItem) {
            if (importItem?.import_status === 'completed') return 'Abgeschlossen'
            if (importItem?.import_status === 'failed') return 'Fehlgeschlagen'
            if (importItem?.import_status === 'running') return 'Läuft'
            if (importItem?.import_status === 'pending') return 'Wartet'
            if (importItem?.import_status === 'deleting') return 'Löschen läuft'

            return 'Unbekannt'
        },
        statusColor(importItem) {
            if (importItem?.import_status === 'completed') return 'success'
            if (importItem?.import_status === 'failed') return 'error'
            if (this.importIsProcessing(importItem)) return 'info'

            return 'default'
        },
        formatDate(dateStr) {
            if (!dateStr) return ''
            const d = new Date(dateStr)
            return d.toLocaleDateString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            })
        },
    },
}
</script>

<style scoped>
.tt-sub-row td {
    border-top: none !important;
    padding-top: 0 !important;
    color: rgba(30, 64, 175, 0.8);
}

.main-import-summary {
    border: 1px solid rgba(25, 118, 210, 0.22);
    border-radius: 8px;
    padding: 12px;
    background: #f8fbff;
}

.import-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
}

.main-import-meta-grid {
    grid-template-columns: minmax(230px, 1.6fr) repeat(auto-fit, minmax(150px, 1fr));
}

.import-meta-item {
    min-width: 0;
}

.import-meta-item span {
    display: block;
    font-size: 0.72rem;
    color: rgba(0, 0, 0, 0.6);
}

.import-meta-item strong {
    display: block;
    font-size: 0.86rem;
    overflow-wrap: anywhere;
}

.main-import-meta-grid .import-meta-item:first-child strong {
    white-space: nowrap;
}

.import-history-panel {
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.import-history-panel + .import-history-panel {
    margin-top: 6px;
}

.import-history-title {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-width: 0;
}

.import-history-unimport-button {
    flex: 0 0 auto;
}

.main-dataset-course-panel {
    border: 1px solid rgba(25, 118, 210, 0.16);
}

.main-dataset-course-title {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-width: 0;
}
</style>
