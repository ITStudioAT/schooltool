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
                <v-alert v-if="currentImport" type="warning" variant="tonal" class="mb-3">
                    Es existiert bereits eine Datei für dieses Schuljahr. Ein neuer Import ersetzt die bestehende Datei.
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
                <v-icon icon="mdi-file-document-outline" />
                Importierte Datei
                <v-chip v-if="schoolyearName" size="small" variant="tonal" color="light-blue">
                    {{ schoolyearName }}
                </v-chip>
            </v-card-title>
            <v-card-text>
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-2" />

                <v-alert v-if="!loading && !currentImport" type="info" variant="tonal">
                    Noch keine Datei für dieses Schuljahr importiert.
                </v-alert>

                <template v-if="currentImport">
                    <div class="d-flex align-center ga-2 mb-3">
                        <v-icon icon="mdi-file-document-outline" color="primary" />
                        <div class="flex-grow-1">
                            <div class="font-weight-medium">{{ currentImport.original_filename }}</div>
                            <div class="text-caption text-medium-emphasis">
                                Importiert am {{ formatDate(currentImport.imported_at) }}
                            </div>
                        </div>
                        <v-btn
                            icon="mdi-delete-outline"
                            size="small"
                            variant="text"
                            color="error"
                            :disabled="importIsProcessing"
                            @click="deleteDialog = true" />
                    </div>

                    <v-alert v-if="importIsProcessing" type="info" variant="tonal" class="mb-3">
                        <div class="mb-2">{{ importStatusText }}</div>
                        <v-progress-linear
                            :model-value="importProgress"
                            :indeterminate="!currentImport.progress_total"
                            color="primary" />
                    </v-alert>

                    <v-alert v-if="currentImport.import_status === 'failed'" type="error" variant="tonal" class="mb-3">
                        {{ currentImport.import_message || 'Import fehlgeschlagen.' }}
                    </v-alert>

                    <v-table v-if="currentImport.import_status === 'completed'" density="comfortable">
                        <thead>
                            <tr>
                                <th>Sektion</th>
                                <th>Beschreibung</th>
                                <th class="text-right">Anzahl</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(count, code) in currentImport.sections" :key="code">
                                <tr>
                                    <td>
                                        <v-chip size="small" color="primary" variant="tonal">{{ code }}</v-chip>
                                    </td>
                                    <td>{{ sectionLabel(code) }}</td>
                                    <td class="text-right font-weight-medium">{{ count }}</td>
                                </tr>
                                <tr v-if="code === 'TT' && currentImport.tt_courses" class="tt-sub-row">
                                    <td></td>
                                    <td class="text-caption pl-6">davon verschiedene Kurse</td>
                                    <td class="text-right font-weight-medium text-caption">{{ currentImport.tt_courses }}</td>
                                </tr>
                                <tr v-if="code === 'TT' && currentImport.tt_first_date" class="tt-sub-row">
                                    <td></td>
                                    <td class="text-caption pl-6">Zeitraum</td>
                                    <td class="text-right font-weight-medium text-caption">
                                        {{ currentImport.tt_first_date }} &mdash; {{ currentImport.tt_last_date }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="font-weight-bold">Gesamt</td>
                                <td class="text-right font-weight-bold">{{ currentImport.total_lines }}</td>
                            </tr>
                        </tfoot>
                    </v-table>
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
                    Soll die importierte Datei
                    <strong>{{ currentImport?.original_filename }}</strong>
                    unwiderruflich gelöscht werden?
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="deleting" @click="deleteDialog = false">Abbrechen</v-btn>
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
            currentImport: null,
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
        semester2StartRaw() {
            return this.config?.selected_schoolyear?.sem_2_start || ''
        },
        semester2Start() {
            const date = this.semester2StartRaw
            if (!date) return ''
            const d = new Date(date)
            return d.toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        importIsProcessing() {
            return ['pending', 'running'].includes(this.currentImport?.import_status)
        },
        importProgress() {
            const current = Number(this.currentImport?.progress_current || 0)
            const total = Number(this.currentImport?.progress_total || 0)
            if (!total) return 0

            return Math.min(100, Math.round((current / total) * 100))
        },
        importStatusText() {
            if (!this.currentImport) return ''

            return this.currentImport.import_message || 'Import wird verarbeitet.'
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
                    params: { page: 1 },
                })
                this.currentImport = response.data.data?.[0] || null
                this.updatePolling()
            } catch {
                this.currentImport = null
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
        async deleteImport() {
            this.deleting = true
            try {
                await axios.delete(`/api/admin/students-timetables/imports/${this.currentImport.id}`)
                this.currentImport = null
                this.deleteDialog = false
                this.clearPolling()
            } finally {
                this.deleting = false
            }
        },
        updatePolling() {
            if (this.importIsProcessing) {
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
</style>
