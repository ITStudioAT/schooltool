<template>
    <v-col cols="12" md="6" lg="7" xl="4">
        <v-sheet rounded="lg" class="import-subnav mb-4">
            <v-btn
                v-for="item in importNavigationItems"
                :key="item.key"
                size="small"
                :color="import_action === item.key ? 'primary' : 'secondary'"
                :variant="import_action === item.key ? 'flat' : 'tonal'"
                :prepend-icon="item.icon"
                class="import-subnav__button"
                @click="handleImportNavigation(item.key)">
                {{ item.label }}
            </v-btn>
        </v-sheet>

        <v-card v-if="import_action === 'import'" rounded="lg" border class="mb-4">
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

        <v-card v-if="import_action === 'overview'" rounded="lg" border>
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
                            v-if="activeDatasetCourses.length || activeDatasetSingleDateCourses.length"
                            variant="accordion"
                            multiple
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
                                    <v-alert v-if="singleDateActivationError" type="error" variant="tonal" class="mb-3">
                                        {{ singleDateActivationError }}
                                    </v-alert>
                                    <v-table density="compact">
                                        <thead>
                                            <tr>
                                                <th>Kurs</th>
                                                <th class="text-right">Wochenstd.</th>
                                                <th class="text-right">Einträge</th>
                                                <th>Zeitraum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="courseItem in activeDatasetCourses" :key="courseItem.name">
                                                <td class="font-weight-medium">{{ courseItem.name }}</td>
                                                <td class="text-right">{{ datasetCourseWeeklyHoursLabel(courseItem) }}</td>
                                                <td class="text-right">{{ courseItem.entries_count }}</td>
                                                <td>{{ datasetCourseDateRangeLabel(courseItem) }}</td>
                                            </tr>
                                        </tbody>
                                    </v-table>
                                </v-expansion-panel-text>
                            </v-expansion-panel>
                            <v-expansion-panel
                                v-if="activeDatasetSingleDateCourses.length"
                                elevation="0"
                                class="main-dataset-course-panel">
                                <v-expansion-panel-title>
                                    <div class="main-dataset-course-title">
                                        <v-icon icon="mdi-calendar-star-outline" color="warning" size="18" />
                                        <span class="font-weight-medium">Einzeltermine</span>
                                        <v-chip size="x-small" color="warning" variant="tonal">
                                            {{ activeDatasetSingleDateAppointmentsCount }}
                                        </v-chip>
                                        <LoadingAnimation
                                            v-if="singleDateActivationSaveInProgress"
                                            class="single-date-saving-dots" />
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <v-table density="compact">
                                        <caption v-if="singleDateActivationSaveInProgress" class="single-date-save-caption">
                                            Änderungen werden im Hintergrund gespeichert.
                                        </caption>
                                        <thead>
                                            <tr>
                                                <th class="single-date-select-col">
                                                    <v-checkbox
                                                        :model-value="allSingleDateAppointmentsActive"
                                                        :indeterminate="partlyActiveSingleDateAppointments"
                                                        label="Aktiv"
                                                        density="compact"
                                                        hide-details
                                                        color="warning"
                                                        @update:model-value="setAllSingleDateAppointmentsActive"
                                                        @click.stop />
                                                </th>
                                                <th>Kurs</th>
                                                <th>Termin</th>
                                                <th>Stunde</th>
                                                <th>Fach</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template
                                                v-for="courseItem in activeDatasetSingleDateCourses"
                                                :key="courseItem.name">
                                                <tr
                                                    class="single-date-course-row"
                                                    :class="{
                                                        'single-date-course-row--inactive': !courseSingleDateAppointmentsActive(courseItem)
                                                            && !courseSingleDateAppointmentsIndeterminate(courseItem),
                                                    }">
                                                    <td class="single-date-select-col">
                                                        <v-checkbox
                                                            :model-value="courseSingleDateAppointmentsActive(courseItem)"
                                                            :indeterminate="courseSingleDateAppointmentsIndeterminate(courseItem)"
                                                            density="compact"
                                                            hide-details
                                                            color="warning"
                                                            @update:model-value="setCourseSingleDateAppointmentsActive(courseItem, $event)"
                                                            @click.stop />
                                                    </td>
                                                    <td colspan="4" class="font-weight-bold">
                                                        {{ courseItem.name }}
                                                        <v-chip size="x-small" color="warning" variant="tonal" class="ml-1">
                                                            {{ courseItem.appointments_count }}
                                                        </v-chip>
                                                    </td>
                                                </tr>
                                                <tr
                                                    v-for="appointment in courseItem.appointments"
                                                    :key="singleDateAppointmentKey(courseItem, appointment)"
                                                    :class="{
                                                        'single-date-appointment-row--inactive': !singleDateAppointmentActive(courseItem, appointment),
                                                    }">
                                                    <td class="single-date-select-col">
                                                        <v-checkbox
                                                            :model-value="singleDateAppointmentActive(courseItem, appointment)"
                                                            density="compact"
                                                            hide-details
                                                            color="warning"
                                                            @update:model-value="setSingleDateAppointmentActive(courseItem, appointment, $event)"
                                                            @click.stop />
                                                    </td>
                                                    <td></td>
                                                    <td>{{ formatDateWithWeekdayLabel(appointment.date) }}</td>
                                                    <td>{{ singleDateAppointmentTimeLabel(appointment) }}</td>
                                                    <td>{{ appointment.subject || '-' }}</td>
                                                </tr>
                                            </template>
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
                                        <span>Importierte TT-Einträge</span>
                                        <strong>{{ importedTtCount(importItem) }}</strong>
                                    </div>
                                    <div class="import-meta-item">
                                        <span>Nicht importierte TT-Einträge</span>
                                        <strong>{{ importItem.tt_skipped_invalid || 0 }}</strong>
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
                                            <tr v-if="code === 'TT'" class="tt-sub-row">
                                                <td></td>
                                                <td class="text-caption pl-6">davon importiert</td>
                                                <td class="text-right font-weight-medium text-caption">{{ importedTtCount(importItem) }}</td>
                                            </tr>
                                            <tr v-if="code === 'TT' && importItem.tt_skipped_invalid" class="tt-sub-row">
                                                <td></td>
                                                <td class="text-caption pl-6">
                                                    nicht importiert: 2. Spalte = 0 oder 8. Spalte ohne Kurs
                                                </td>
                                                <td class="text-right font-weight-medium text-caption">{{ importItem.tt_skipped_invalid }}</td>
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
import LoadingAnimation from '@/pages/components/LoadingAnimation.vue'

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
    components: { FileUpload, LoadingAnimation },
    data() {
        return {
            imports: [],
            mainDataset: null,
            deleteTargetImport: null,
            loading: false,
            import_action: this.normalizedImportAction(this.$route.params.subsection),
            deleteDialog: false,
            deleting: false,
            schoolyearDialog: false,
            schoolyearData: {},
            schoolyearValid: false,
            savingSchoolyear: false,
            uploadError: '',
            pollingInterval: null,
            savingSingleDateActivation: false,
            singleDateActivationSaveQueued: false,
            singleDateActivationSaveTimer: null,
            singleDateActivationError: '',
            activeSingleDateAppointmentKeys: [],
            singleDateActivationDatasetSignature: '',
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
        activeDatasetSingleDateCourses() {
            return Array.isArray(this.mainDataset?.single_date_courses) ? this.mainDataset.single_date_courses : []
        },
        activeDatasetSingleDateAppointmentsCount() {
            return this.activeDatasetSingleDateCourses
                .reduce((total, courseItem) => total + Number(courseItem.appointments_count || 0), 0)
        },
        allSingleDateAppointmentKeys() {
            return this.activeDatasetSingleDateCourses.flatMap(courseItem =>
                (courseItem.appointments || []).map(appointment => this.singleDateAppointmentKey(courseItem, appointment)),
            )
        },
        singleDateActivationDatasetStateSignature() {
            return this.activeDatasetSingleDateCourses
                .flatMap(courseItem => (courseItem.appointments || []).map(appointment => [
                    this.singleDateAppointmentKey(courseItem, appointment),
                    appointment.active === false ? 'inactive' : 'active',
                ].join(':')))
                .join('|')
        },
        activeSingleDateAppointmentKeySet() {
            return new Set(this.activeSingleDateAppointmentKeys)
        },
        allSingleDateAppointmentsActive() {
            return this.allSingleDateAppointmentKeys.length > 0
                && this.activeSingleDateAppointmentKeys.length === this.allSingleDateAppointmentKeys.length
        },
        partlyActiveSingleDateAppointments() {
            return this.activeSingleDateAppointmentKeys.length > 0 && !this.allSingleDateAppointmentsActive
        },
        singleDateActivationSaveInProgress() {
            return this.savingSingleDateActivation || this.singleDateActivationSaveQueued
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
        importNavigationItems() {
            return [
                {
                    key: 'overview',
                    label: 'Überblick',
                    icon: 'mdi-view-dashboard-outline',
                },
                {
                    key: 'import',
                    label: 'Import',
                    icon: 'mdi-upload',
                },
            ]
        },
    },
    watch: {
        'config.selected_schoolyear.id'() {
            this.loadImport()
        },
        '$route.params.subsection'(subsection) {
            this.import_action = this.normalizedImportAction(subsection)
        },
    },
    mounted() {
        this.loadImport()
    },
    unmounted() {
        this.clearPolling()
        this.clearSingleDateActivationSaveTimer()
    },
    methods: {
        normalizedImportAction(subsection) {
            const allowedActions = ['overview', 'import']

            return allowedActions.includes(subsection) ? subsection : 'overview'
        },
        handleImportNavigation(key) {
            this.import_action = this.normalizedImportAction(key)
            this.$router.push({ path: `/admin/students-timetables/import/${this.import_action}` })
        },
        async loadImport() {
            this.loading = true
            this.uploadError = ''
            try {
                const response = await axios.get('/api/admin/students-timetables/imports', {
                    params: { page: 1, per_page: 100 },
                })
                this.imports = response.data.data || []
                this.mainDataset = response.data.main_dataset || null
                if (!this.singleDateActivationSaveInProgress) {
                    this.syncSingleDateAppointmentActivation()
                }
                this.updatePolling()
            } catch {
                this.imports = []
                this.mainDataset = null
                this.activeSingleDateAppointmentKeys = []
                this.singleDateActivationDatasetSignature = ''
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
        importedTtCount(importItem) {
            const timetableRecords = Number(importItem?.sections?.TT || 0)
            const skippedRecords = Number(importItem?.tt_skipped_invalid || 0)

            return Math.max(0, timetableRecords - skippedRecords)
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
        datasetCourseWeeklyHoursLabel(courseItem) {
            const weeklyHours = Number(courseItem?.weekly_hours || 0)

            return weeklyHours > 0 ? `${weeklyHours}` : '-'
        },
        syncSingleDateAppointmentActivation() {
            const appointmentKeys = this.allSingleDateAppointmentKeys
            const datasetSignature = this.singleDateActivationDatasetStateSignature

            if (datasetSignature !== this.singleDateActivationDatasetSignature) {
                this.activeSingleDateAppointmentKeys = this.activeDatasetSingleDateCourses
                    .flatMap(courseItem => (courseItem.appointments || [])
                        .filter(appointment => appointment.active !== false)
                        .map(appointment => this.singleDateAppointmentKey(courseItem, appointment)))
                this.singleDateActivationDatasetSignature = datasetSignature

                return
            }

            const appointmentKeySet = new Set(appointmentKeys)
            this.activeSingleDateAppointmentKeys = this.activeSingleDateAppointmentKeys
                .filter(appointmentKey => appointmentKeySet.has(appointmentKey))
        },
        setAllSingleDateAppointmentsActive(active) {
            this.activeSingleDateAppointmentKeys = active ? [...this.allSingleDateAppointmentKeys] : []
            this.queueSingleDateAppointmentActivationSave()
        },
        courseSingleDateAppointmentKeys(courseItem) {
            return (courseItem?.appointments || []).map(appointment => this.singleDateAppointmentKey(courseItem, appointment))
        },
        courseSingleDateAppointmentsActive(courseItem) {
            const appointmentKeys = this.courseSingleDateAppointmentKeys(courseItem)

            return appointmentKeys.length > 0
                && appointmentKeys.every(appointmentKey => this.activeSingleDateAppointmentKeySet.has(appointmentKey))
        },
        courseSingleDateAppointmentsIndeterminate(courseItem) {
            const appointmentKeys = this.courseSingleDateAppointmentKeys(courseItem)
            const activeCount = appointmentKeys
                .filter(appointmentKey => this.activeSingleDateAppointmentKeySet.has(appointmentKey))
                .length

            return activeCount > 0 && activeCount < appointmentKeys.length
        },
        setCourseSingleDateAppointmentsActive(courseItem, active) {
            const activeKeys = new Set(this.activeSingleDateAppointmentKeys)

            this.courseSingleDateAppointmentKeys(courseItem).forEach(appointmentKey => {
                if (active) {
                    activeKeys.add(appointmentKey)

                    return
                }

                activeKeys.delete(appointmentKey)
            })

            this.activeSingleDateAppointmentKeys = [...activeKeys]
            this.queueSingleDateAppointmentActivationSave()
        },
        singleDateAppointmentActive(courseItem, appointment) {
            return this.activeSingleDateAppointmentKeySet.has(this.singleDateAppointmentKey(courseItem, appointment))
        },
        setSingleDateAppointmentActive(courseItem, appointment, active) {
            const appointmentKey = this.singleDateAppointmentKey(courseItem, appointment)
            const activeKeys = new Set(this.activeSingleDateAppointmentKeys)

            if (active) {
                activeKeys.add(appointmentKey)
            } else {
                activeKeys.delete(appointmentKey)
            }

            this.activeSingleDateAppointmentKeys = [...activeKeys]
            this.queueSingleDateAppointmentActivationSave()
        },
        singleDateActivationPayload() {
            const activeKeys = this.activeSingleDateAppointmentKeySet

            return this.activeDatasetSingleDateCourses
                .flatMap(courseItem => (courseItem.appointments || []).map(appointment => ({
                    entry_ids: Array.isArray(appointment.entry_ids) ? appointment.entry_ids : [],
                    active: activeKeys.has(this.singleDateAppointmentKey(courseItem, appointment)),
                })))
                .filter(appointment => appointment.entry_ids.length > 0)
        },
        queueSingleDateAppointmentActivationSave() {
            this.singleDateActivationError = ''
            this.singleDateActivationSaveQueued = true

            if (this.savingSingleDateActivation) return

            this.clearSingleDateActivationSaveTimer()
            this.singleDateActivationSaveTimer = window.setTimeout(() => {
                this.singleDateActivationSaveTimer = null
                this.saveSingleDateAppointmentActivation()
            }, 350)
        },
        clearSingleDateActivationSaveTimer() {
            if (!this.singleDateActivationSaveTimer) return

            window.clearTimeout(this.singleDateActivationSaveTimer)
            this.singleDateActivationSaveTimer = null
        },
        async saveSingleDateAppointmentActivation() {
            this.clearSingleDateActivationSaveTimer()

            if (this.savingSingleDateActivation) {
                this.singleDateActivationSaveQueued = true

                return
            }

            this.savingSingleDateActivation = true
            this.singleDateActivationSaveQueued = false
            this.singleDateActivationError = ''

            try {
                const response = await axios.put('/api/admin/students-timetables/imports/single-date-appointments', {
                    appointments: this.singleDateActivationPayload(),
                })

                if (!this.singleDateActivationSaveQueued) {
                    this.mainDataset = response.data.main_dataset || this.mainDataset
                    this.syncSingleDateAppointmentActivation()
                }
            } catch {
                this.singleDateActivationError = 'Die Aktivierung der Einzeltermine konnte nicht gespeichert werden.'
            } finally {
                this.savingSingleDateActivation = false

                if (this.singleDateActivationSaveQueued) {
                    this.queueSingleDateAppointmentActivationSave()
                }
            }
        },
        singleDateAppointmentKey(courseItem, appointment) {
            return [
                courseItem?.name || '',
                appointment?.date || '',
                appointment?.period || '',
                appointment?.starts_at || '',
                appointment?.subject || '',
                appointment?.teacher || '',
            ].join('|')
        },
        singleDateAppointmentTimeLabel(appointment) {
            const period = appointment?.period ? `${appointment.period}. Std.` : ''
            const timeRange = [appointment?.starts_at, appointment?.ends_at].filter(Boolean).join('-')

            return [period, timeRange].filter(Boolean).join(' ')
        },
        formatDateWithWeekdayLabel(value) {
            const weekdayLabel = this.weekdayLabelForDate(value)
            const dateLabel = this.formatDateLabel(value)

            return [weekdayLabel, dateLabel].filter(Boolean).join(', ')
        },
        weekdayLabelForDate(value) {
            const dateValue = String(value || '').trim()
            const match = dateValue.match(/^(\d{4})-(\d{2})-(\d{2})$/u)

            if (!match) return ''

            const [, year, month, day] = match
            const weekday = new Date(Number(year), Number(month) - 1, Number(day)).getDay()
            const weekdays = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa']

            return weekdays[weekday] || 'So'
        },
        formatDateLabel(value) {
            const dateValue = String(value || '').trim()
            const match = dateValue.match(/^(\d{4})-(\d{2})-(\d{2})$/u)

            if (match) return `${match[3]}.${match[2]}.${match[1]}`

            return dateValue
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
.import-subnav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px;
    border: 1px solid rgba(37, 99, 235, 0.16);
    background: rgba(255, 255, 255, 0.88);
}

.import-subnav__button {
    background: #dbeafe !important;
    border: 1px solid rgba(37, 99, 235, 0.22) !important;
    color: #1e3a8a !important;
    text-transform: none;
    letter-spacing: 0;
}

.import-subnav__button.v-btn--variant-flat {
    background: rgb(var(--v-theme-primary)) !important;
    border-color: rgba(30, 64, 175, 0.52) !important;
    color: rgb(var(--v-theme-on-primary)) !important;
}

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

.single-date-saving-dots {
    padding: 0;
}

.single-date-saving-dots :deep(.loading-animation) {
    padding: 0;
}

.single-date-saving-dots :deep(.loading-dots) {
    gap: 5px;
}

.single-date-saving-dots :deep(.dot) {
    width: 7px;
    height: 7px;
}

.single-date-save-caption {
    caption-side: top;
    padding: 6px 8px 10px;
    color: rgba(0, 0, 0, 0.62);
    font-size: 0.78rem;
    text-align: left;
}

.single-date-course-row td {
    background: rgba(251, 140, 0, 0.08);
    border-top: 1px solid rgba(251, 140, 0, 0.18);
}

.single-date-select-col {
    width: 116px;
    min-width: 116px;
    white-space: nowrap;
}

.single-date-course-row--inactive td,
.single-date-appointment-row--inactive td {
    color: rgba(0, 0, 0, 0.46);
    text-decoration: line-through;
}

@media (max-width: 640px) {
    .main-import-meta-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .single-date-select-col {
        width: 56px;
        min-width: 56px;
    }

    .import-history-title {
        flex-wrap: wrap;
    }

    .import-history-unimport-button {
        flex-basis: 100%;
    }
}
</style>
