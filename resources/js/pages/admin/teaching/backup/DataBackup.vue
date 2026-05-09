<template>
    <v-col cols="12" class="teaching-data-backup-col">
        <section class="teaching-data-backup-shell">
            <div class="teaching-data-backup-header">
                <div>
                    <div class="text-subtitle-1 font-weight-bold">Datensicherung</div>
                    <div class="text-body-2 text-medium-emphasis">
                        Sichert die Unterrichtsdaten der aktiven Schule und des aktiven Schuljahres.
                    </div>
                </div>
                <div class="teaching-data-backup-actions">
                    <input
                        ref="backup_import_input"
                        type="file"
                        accept="application/json,.json"
                        class="d-none"
                        @change="importBackup" />
                    <v-btn color="secondary" variant="tonal" prepend-icon="mdi-upload-outline" :loading="importing" @click="selectImportFile">
                        Backup-Datei importieren
                    </v-btn>
                    <v-btn color="primary" variant="flat" prepend-icon="mdi-database-plus-outline" :loading="creating" @click="createBackup">
                        Neue Datensicherung
                    </v-btn>
                </div>
            </div>

            <v-alert v-if="error" type="error" variant="tonal" density="comfortable" class="ma-4 mt-0">
                {{ error }}
            </v-alert>
            <v-alert v-if="import_notice" type="info" variant="tonal" density="comfortable" class="ma-4 mt-0">
                {{ import_notice }}
            </v-alert>

            <div v-if="loading" class="teaching-data-backup-state">
                <v-progress-circular indeterminate color="primary" size="24" />
            </div>

            <div v-else-if="!backups.length" class="teaching-data-backup-empty">
                <v-icon size="28" color="primary">mdi-database-arrow-down-outline</v-icon>
                <div>
                    <div class="text-body-1 font-weight-medium">Noch keine Datensicherung vorhanden</div>
                    <div class="text-body-2 text-medium-emphasis">
                        Erstelle eine Sicherung, um den aktuellen Stand später wiederherstellen zu können.
                    </div>
                </div>
            </div>

            <v-table v-else density="comfortable" class="teaching-data-backup-table">
                <thead>
                    <tr>
                        <th>Erstellt</th>
                        <th>Status</th>
                        <th>Datensicherung</th>
                        <th>Datensätze</th>
                        <th>Dateien</th>
                        <th class="text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="backup in backups" :key="backup.id">
                        <td>{{ formatDateTime(backup.created_at) }}</td>
                        <td>
                            <v-chip size="small" variant="tonal" :color="validationColor(backup)">
                                {{ validationLabel(backup) }}
                            </v-chip>
                        </td>
                        <td class="teaching-data-backup-file">{{ backupDisplayTitle(backup) }}</td>
                        <td>{{ backup.summary?.total_rows ?? 0 }}</td>
                        <td>
                            {{ backup.summary?.file_count ?? 0 }}
                            <span v-if="backup.summary?.missing_file_count" class="text-warning">
                                / {{ backup.summary.missing_file_count }} fehlt
                            </span>
                        </td>
                        <td class="text-right">
                            <v-btn
                                icon="mdi-clipboard-search-outline"
                                variant="text"
                                color="primary"
                                size="small"
                                title="Wiederherstellung prüfen"
                                :loading="preview_loading_id === backup.id"
                                @click="openRestorePreview(backup)" />
                            <v-btn
                                icon="mdi-information-outline"
                                variant="text"
                                color="secondary"
                                size="small"
                                title="Details anzeigen"
                                @click="openDetails(backup)" />
                            <v-btn
                                icon="mdi-download-outline"
                                variant="text"
                                color="primary"
                                size="small"
                                title="Datensicherung herunterladen"
                                @click="downloadBackup(backup)" />
                            <v-btn
                                icon="mdi-delete-outline"
                                variant="text"
                                color="error"
                                size="small"
                                title="Datensicherung löschen"
                                :loading="delete_loading_id === backup.id"
                                @click="openDeleteDialog(backup)" />
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </section>

        <section v-if="restore_runs.length" class="teaching-data-backup-shell mt-4">
            <div class="teaching-data-backup-header">
                <div>
                    <div class="text-subtitle-1 font-weight-bold">Wiederherstellungsverlauf</div>
                </div>
            </div>
            <v-table density="compact" class="teaching-data-backup-table">
                <thead>
                    <tr>
                        <th>Zeitpunkt</th>
                        <th>Art</th>
                        <th>Status</th>
                        <th>Datensicherung</th>
                        <th>Sicherheitskopie</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="run in restore_runs" :key="run.id">
                        <td>{{ formatDateTime(run.created_at) }}</td>
                        <td>{{ restoreRunTypeLabel(run.type) }}</td>
                        <td>
                            <v-chip size="x-small" variant="tonal" :color="restoreRunStatusColor(run.status)">
                                {{ restoreRunStatusLabel(run.status) }}
                            </v-chip>
                        </td>
                        <td class="teaching-data-backup-file">{{ run.backup_filename || '-' }}</td>
                        <td class="teaching-data-backup-file">{{ run.pre_restore_backup_filename || '-' }}</td>
                    </tr>
                </tbody>
            </v-table>
        </section>

        <v-dialog v-model="details_open" max-width="760">
            <v-card v-if="selected_backup" rounded="lg">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon size="20" :color="validationColor(selected_backup)">mdi-database-check-outline</v-icon>
                    Details zur Datensicherung
                </v-card-title>
                <v-card-text class="px-4 pb-2">
                    <div class="teaching-data-backup-detail-grid">
                        <div>
                            <div class="text-caption text-medium-emphasis">Datensicherung</div>
                            <div class="text-body-2 font-weight-medium">{{ backupDisplayTitle(selected_backup) }}</div>
                        </div>
                        <div>
                            <div class="text-caption text-medium-emphasis">Erstellt</div>
                            <div class="text-body-2 font-weight-medium">{{ formatDateTime(selected_backup.created_at) }}</div>
                        </div>
                        <div>
                            <div class="text-caption text-medium-emphasis">Status</div>
                            <v-chip size="small" variant="tonal" :color="validationColor(selected_backup)">
                                {{ validationLabel(selected_backup) }}
                            </v-chip>
                        </div>
                        <div>
                            <div class="text-caption text-medium-emphasis">Datensätze</div>
                            <div class="text-body-2 font-weight-medium">{{ selected_backup.summary?.total_rows ?? 0 }}</div>
                        </div>
                        <div>
                            <div class="text-caption text-medium-emphasis">Dateien</div>
                            <div class="text-body-2 font-weight-medium">
                                {{ selected_backup.summary?.file_count ?? 0 }}
                                <span v-if="selected_backup.summary?.missing_file_count" class="text-warning">
                                    / {{ selected_backup.summary.missing_file_count }} fehlt
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="text-caption text-medium-emphasis">Dateiname</div>
                            <div class="text-body-2 font-weight-medium teaching-data-backup-file">{{ selected_backup.filename }}</div>
                        </div>
                    </div>

                    <v-alert
                        v-if="validationIssues(selected_backup).length"
                        type="error"
                        variant="tonal"
                        density="comfortable"
                        class="mt-4">
                        <div v-for="issue in validationIssues(selected_backup)" :key="issue">{{ issue }}</div>
                    </v-alert>

                    <v-alert
                        v-if="validationWarnings(selected_backup).length"
                        type="warning"
                        variant="tonal"
                        density="comfortable"
                        class="mt-4">
                        <div v-for="warning in validationWarnings(selected_backup)" :key="warning">{{ warning }}</div>
                    </v-alert>

                    <div class="text-subtitle-2 font-weight-bold mt-5 mb-2">Gesicherte Bereiche</div>
                    <v-table density="compact" class="teaching-data-backup-counts">
                        <tbody>
                            <tr v-for="entry in tableCountEntries(selected_backup)" :key="entry.key">
                                <td>{{ entry.label }}</td>
                                <td class="text-right">{{ entry.count }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="tonal" @click="details_open = false">Schließen</v-btn>
                    <v-btn color="primary" variant="flat" prepend-icon="mdi-download-outline" @click="downloadBackup(selected_backup)">
                        Herunterladen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="preview_open" max-width="980" persistent>
            <v-card rounded="lg">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon size="20" color="primary">mdi-clipboard-search-outline</v-icon>
                    Wiederherstellung prüfen
                </v-card-title>
                <v-card-text class="px-4 pb-2">
                    <v-alert v-if="preview_error" type="error" variant="tonal" density="comfortable">
                        {{ preview_error }}
                    </v-alert>

                    <div v-else-if="!selected_preview" class="teaching-data-backup-state">
                        <v-progress-circular indeterminate color="primary" size="24" />
                    </div>

                    <template v-else>
                        <div class="teaching-data-backup-detail-grid">
                            <div>
                                <div class="text-caption text-medium-emphasis">Status</div>
                                <v-chip size="small" variant="tonal" :color="previewStatusColor(selected_preview)">
                                    {{ previewStatusLabel(selected_preview) }}
                                </v-chip>
                            </div>
                            <div>
                                <div class="text-caption text-medium-emphasis">Datensätze</div>
                                <div class="text-body-2 font-weight-medium">{{ selected_preview.totals?.rows ?? 0 }}</div>
                            </div>
                            <div>
                                <div class="text-caption text-medium-emphasis">Dateien</div>
                                <div class="text-body-2 font-weight-medium">{{ selected_preview.totals?.files ?? 0 }}</div>
                            </div>
                            <div>
                                <div class="text-caption text-medium-emphasis">Fehlende Dateien</div>
                                <div class="text-body-2 font-weight-medium">{{ selected_preview.totals?.missing_files ?? 0 }}</div>
                            </div>
                        </div>

                        <div class="teaching-data-backup-restore-bar mt-4">
                            <v-checkbox-btn
                                v-model="allRestoreSelected"
                                density="compact"
                                label="Alle wiederherstellbaren auswählen"
                                hide-details />
                            <v-checkbox-btn
                                v-model="overwrite_existing"
                                density="compact"
                                label="Vorhandene Kurse/Curricula überschreiben"
                                hide-details
                                @update:model-value="initializeRestoreSelection" />
                            <div class="text-body-2 text-medium-emphasis">{{ restoreSelectionCount }} ausgewählt</div>
                        </div>

                        <v-alert v-if="restore_error" type="error" variant="tonal" density="comfortable" class="mt-3">
                            {{ restore_error }}
                        </v-alert>

                        <v-alert v-if="restore_result" type="success" variant="tonal" density="comfortable" class="mt-3">
                            <div>{{ restoreResultLabel(restore_result) }}</div>
                            <div v-if="fullRestoreUserReport(restore_result)" class="text-body-2 mt-1">
                                {{ fullRestoreUserReport(restore_result) }}
                            </div>
                            <v-btn size="small" variant="text" class="mt-2 px-0" @click="restore_report_open = true">
                                Details anzeigen
                            </v-btn>
                        </v-alert>

                        <div class="text-subtitle-2 font-weight-bold mt-5 mb-2">Einstellungen</div>
                        <v-table density="compact" class="teaching-data-backup-counts">
                            <thead>
                                <tr>
                                    <th class="teaching-data-backup-select-col"></th>
                                    <th>Bereich</th>
                                    <th class="text-right">Gesichert</th>
                                    <th>Beschreibung</th>
                                    <th class="text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="section in previewSettingSections(selected_preview)" :key="section.key">
                                    <td>
                                        <v-checkbox-btn
                                            :model-value="isRestoreSelected('settings', section.key, section)"
                                            :disabled="!isSettingRestoreSelectable(section)"
                                            density="compact"
                                            hide-details
                                            @update:model-value="toggleRestoreSelection('settings', section.key, $event)" />
                                    </td>
                                    <td>{{ section.label }}</td>
                                    <td class="text-right">{{ sectionCountLabel(section) }}</td>
                                    <td>{{ section.description || '-' }}</td>
                                    <td class="text-right">
                                        <v-chip size="x-small" variant="tonal" :color="settingSectionStatusColor(section)">
                                            {{ settingSectionStatusLabel(section) }}
                                        </v-chip>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>

                        <div class="text-subtitle-2 font-weight-bold mt-5 mb-2">Kurse</div>
                        <v-table density="compact" class="teaching-data-backup-counts">
                            <thead>
                                <tr>
                                    <th class="teaching-data-backup-select-col"></th>
                                    <th>Kurs</th>
                                    <th>Lehrer:in</th>
                                    <th>Klassen</th>
                                    <th class="text-right">Schüler:innen</th>
                                    <th class="text-right">Termine</th>
                                    <th class="text-right">Einträge</th>
                                    <th class="text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="course in selected_preview.courses" :key="course.id">
                                    <td>
                                        <v-checkbox-btn
                                            :model-value="isRestoreSelected('courses', course.id, course)"
                                            :disabled="!isCourseRestoreSelectable(course)"
                                            density="compact"
                                            hide-details
                                            @update:model-value="toggleRestoreSelection('courses', course.id, $event)" />
                                    </td>
                                    <td>{{ course.title }}</td>
                                    <td>{{ course.teacher || '-' }}</td>
                                    <td>{{ classesLabel(course.classes) }}</td>
                                    <td class="text-right">{{ course.student_count }}</td>
                                    <td class="text-right">{{ course.date_count }}</td>
                                    <td class="text-right">{{ course.entry_count + course.behaviour_count }}</td>
                                    <td class="text-right">
                                        <v-chip size="x-small" variant="tonal" :color="restoreStatusColor(course.status)">
                                            {{ restoreStatusLabel(course.status) }}
                                        </v-chip>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>

                        <div class="text-subtitle-2 font-weight-bold mt-5 mb-2">Curricula</div>
                        <v-table density="compact" class="teaching-data-backup-counts">
                            <thead>
                                <tr>
                                    <th class="teaching-data-backup-select-col"></th>
                                    <th>Curriculum</th>
                                    <th class="text-right">Themen</th>
                                    <th class="text-right">Dokumente</th>
                                    <th class="text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="curriculum in selected_preview.curricula" :key="curriculum.id">
                                    <td>
                                        <v-checkbox-btn
                                            :model-value="isRestoreSelected('curricula', curriculum.id, curriculum)"
                                            :disabled="!isCurriculumRestoreSelectable(curriculum)"
                                            density="compact"
                                            hide-details
                                            @update:model-value="toggleRestoreSelection('curricula', curriculum.id, $event)" />
                                    </td>
                                    <td>{{ curriculum.title }}</td>
                                    <td class="text-right">{{ curriculum.topic_count }}</td>
                                    <td class="text-right">{{ curriculum.document_count }}</td>
                                    <td class="text-right">
                                        <v-chip size="x-small" variant="tonal" :color="restoreStatusColor(curriculum.status)">
                                            {{ restoreStatusLabel(curriculum.status) }}
                                        </v-chip>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </template>
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="tonal" @click="preview_open = false">Schließen</v-btn>
                    <v-btn
                        color="error"
                        variant="tonal"
                        prepend-icon="mdi-database-refresh-outline"
                        :disabled="!selected_preview || restore_loading || full_restore_loading"
                        :loading="full_restore_loading"
                        @click="openFullRestoreDialog">
                        Vollständig wiederherstellen
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-database-sync-outline"
                        :disabled="!canRestoreSelected || full_restore_loading"
                        :loading="restore_loading"
                        @click="restoreSelected">
                        Wiederherstellen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="restore_report_open" max-width="760" persistent>
            <v-card v-if="restore_result" rounded="lg">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon size="20" color="success">mdi-clipboard-check-outline</v-icon>
                    Wiederherstellungsbericht
                </v-card-title>
                <v-card-text class="px-4 pb-2">
                    <v-alert v-if="restore_result.pre_restore_backup" type="info" variant="tonal" density="comfortable" class="mb-4">
                        Sicherheitskopie erstellt: {{ backupDisplayTitle(restore_result.pre_restore_backup) }}
                    </v-alert>
                    <v-table density="compact" class="teaching-data-backup-counts">
                        <tbody>
                            <tr v-for="entry in restoreReportEntries(restore_result)" :key="entry.key">
                                <td>{{ entry.label }}</td>
                                <td class="text-right">{{ entry.count }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                    <div v-if="restoreUserReconciliationEntries(restore_result).length" class="text-subtitle-2 font-weight-bold mt-5 mb-2">
                        Benutzer:innen
                    </div>
                    <v-table v-if="restoreUserReconciliationEntries(restore_result).length" density="compact" class="teaching-data-backup-counts">
                        <thead>
                            <tr>
                                <th>Art</th>
                                <th>Name</th>
                                <th>E-Mail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in restoreUserReconciliationEntries(restore_result)" :key="`${entry.type}-${entry.user_id}`">
                                <td>{{ entry.type }}</td>
                                <td>{{ entry.name || '-' }}</td>
                                <td>{{ entry.email || '-' }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="tonal" @click="restore_report_open = false">Schließen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="delete_confirm_open" max-width="520" persistent>
            <v-card v-if="selected_delete_backup" rounded="lg">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon size="20" color="error">mdi-delete-alert-outline</v-icon>
                    Datensicherung löschen
                </v-card-title>
                <v-card-text class="px-4 pb-2">
                    <div class="text-body-2">
                        Diese Datensicherung wird aus der Liste entfernt und die gespeicherte Backup-Datei wird gelöscht.
                    </div>
                    <div class="text-body-2 text-medium-emphasis mt-2">
                        {{ backupDisplayTitle(selected_delete_backup) }}
                    </div>
                    <v-alert v-if="delete_error" type="error" variant="tonal" density="comfortable" class="mt-4">
                        {{ delete_error }}
                    </v-alert>
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="tonal" :disabled="delete_loading" @click="closeDeleteDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn color="error" variant="flat" prepend-icon="mdi-delete-outline" :loading="delete_loading" @click="deleteBackup">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="full_restore_confirm_open" max-width="560" persistent>
            <v-card rounded="lg">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon size="20" color="error">mdi-alert-outline</v-icon>
                    Vollständig wiederherstellen
                </v-card-title>
                <v-card-text class="px-4 pb-2">
                    <div class="text-body-2">
                        Die aktuellen Unterrichtsdaten dieses Schuljahres werden durch die ausgewählte Datensicherung ersetzt.
                    </div>
                    <div class="text-body-2 text-medium-emphasis mt-2">
                        Diese Aktion betrifft nur die aktive Schule und das aktive Schuljahr.
                    </div>
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="tonal" :disabled="full_restore_loading" @click="closeFullRestoreDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        prepend-icon="mdi-database-refresh-outline"
                        :loading="full_restore_loading"
                        @click="restoreFull">
                        Vollständig wiederherstellen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import axios from 'axios'

export default {
    data() {
        return {
            backups: [],
            restore_runs: [],
            loading: false,
            creating: false,
            importing: false,
            error: '',
            import_notice: '',
            delete_confirm_open: false,
            selected_delete_backup: null,
            delete_loading: false,
            delete_loading_id: null,
            delete_error: '',
            details_open: false,
            selected_backup: null,
            preview_open: false,
            preview_loading_id: null,
            selected_preview: null,
            preview_error: '',
            selected_preview_backup: null,
            restore_loading: false,
            full_restore_loading: false,
            full_restore_confirm_open: false,
            restore_error: '',
            restore_result: null,
            restore_report_open: false,
            overwrite_existing: false,
            restore_selection: {
                courses: [],
                curricula: [],
                settings: [],
            },
        }
    },

    computed: {
        restoreSelectionCount() {
            return this.restore_selection.courses.length + this.restore_selection.curricula.length + this.restore_selection.settings.length
        },

        canRestoreSelected() {
            return this.selected_preview && this.restoreSelectionCount > 0 && !this.restore_loading
        },

        allRestoreSelected: {
            get() {
                const selectable = this.restoreSelectableValues()

                return (
                    selectable.courses.length + selectable.curricula.length + selectable.settings.length > 0 &&
                    selectable.courses.every((id) => this.restore_selection.courses.includes(id)) &&
                    selectable.curricula.every((id) => this.restore_selection.curricula.includes(id)) &&
                    selectable.settings.every((key) => this.restore_selection.settings.includes(key))
                )
            },
            set(value) {
                this.setAllRestoreSelected(value)
            },
        },
    },

    mounted() {
        this.loadBackups()
    },

    methods: {
        async loadBackups() {
            this.loading = true
            this.error = ''

            try {
                const [backupResponse, restoreRunResponse] = await Promise.all([
                    axios.get('/api/admin/teaching/backups'),
                    axios.get('/api/admin/teaching/backups/restore-runs'),
                ])
                this.backups = Array.isArray(backupResponse.data?.data) ? backupResponse.data.data : []
                this.restore_runs = Array.isArray(restoreRunResponse.data?.data) ? restoreRunResponse.data.data : []
            } catch (error) {
                this.error = error?.response?.data?.message || 'Datensicherungen konnten nicht geladen werden.'
            } finally {
                this.loading = false
            }
        },

        async createBackup() {
            this.creating = true
            this.error = ''

            try {
                const response = await axios.post('/api/admin/teaching/backups')
                const backup = response.data?.data

                if (backup) {
                    this.backups = [backup, ...this.backups.filter((item) => item.id !== backup.id)]
                } else {
                    await this.loadBackups()
                }
            } catch (error) {
                this.error = error?.response?.data?.message || 'Datensicherung konnte nicht erstellt werden.'
            } finally {
                this.creating = false
            }
        },

        selectImportFile() {
            this.$refs.backup_import_input?.click()
        },

        async importBackup(event) {
            const file = event?.target?.files?.[0] || null

            if (!file) {
                return
            }

            this.importing = true
            this.error = ''
            this.import_notice = ''

            const formData = new FormData()
            formData.append('backup', file)

            try {
                const response = await axios.post('/api/admin/teaching/backups/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                })
                const backup = response.data?.data

                if (backup) {
                    this.backups = [backup, ...this.backups.filter((item) => item.id !== backup.id)]
                } else {
                    await this.loadBackups()
                }

                this.import_notice = response.data?.meta?.message || 'Backup-Datei wurde importiert.'
            } catch (error) {
                this.error = error?.response?.data?.message || 'Datensicherung konnte nicht importiert werden.'
            } finally {
                this.importing = false

                if (event?.target) {
                    event.target.value = ''
                }
            }
        },

        downloadBackup(backup) {
            if (!backup?.download_url) {
                return
            }

            window.location.href = backup.download_url
        },

        openDeleteDialog(backup) {
            if (!backup?.id || this.delete_loading) {
                return
            }

            this.selected_delete_backup = backup
            this.delete_error = ''
            this.delete_confirm_open = true
        },

        closeDeleteDialog() {
            if (this.delete_loading) {
                return
            }

            this.delete_confirm_open = false
            this.selected_delete_backup = null
            this.delete_error = ''
        },

        async deleteBackup() {
            if (!this.selected_delete_backup?.id) {
                return
            }

            const backupId = this.selected_delete_backup.id
            this.delete_loading = true
            this.delete_loading_id = backupId
            this.delete_error = ''
            this.error = ''

            try {
                await axios.delete(`/api/admin/teaching/backups/${backupId}`)
                this.backups = this.backups.filter((backup) => backup.id !== backupId)

                if (this.selected_backup?.id === backupId) {
                    this.details_open = false
                    this.selected_backup = null
                }

                if (this.selected_preview_backup?.id === backupId) {
                    this.preview_open = false
                    this.selected_preview = null
                    this.selected_preview_backup = null
                }

                this.delete_confirm_open = false
                this.selected_delete_backup = null
            } catch (error) {
                this.delete_error = error?.response?.data?.message || 'Datensicherung konnte nicht gelöscht werden.'
            } finally {
                this.delete_loading = false
                this.delete_loading_id = null
            }
        },

        backupDisplayTitle(backup) {
            const schoolyear = backup?.schoolyear_name || `Schuljahr-ID ${backup?.schoolyear_id ?? '-'}`
            const createdAt = this.formatDateTime(backup?.created_at)

            return `${schoolyear} - gesichert am ${createdAt}`
        },

        openDetails(backup) {
            this.selected_backup = backup
            this.details_open = true
        },

        async openRestorePreview(backup) {
            this.preview_open = true
            this.preview_error = ''
            this.selected_preview = null
            this.preview_loading_id = backup?.id || null
            this.selected_preview_backup = backup || null
            this.restore_error = ''
            this.restore_result = null
            this.restore_report_open = false
            this.overwrite_existing = false
            this.restore_selection = {
                courses: [],
                curricula: [],
                settings: [],
            }

            try {
                const response = await axios.get(`/api/admin/teaching/backups/${backup.id}/preview`)
                this.selected_preview = response.data?.data || null
                this.initializeRestoreSelection()
            } catch (error) {
                this.preview_error = error?.response?.data?.message || 'Wiederherstellungsprüfung konnte nicht geladen werden.'
            } finally {
                this.preview_loading_id = null
            }
        },

        async restoreSelected() {
            if (!this.canRestoreSelected || !this.selected_preview_backup?.id) {
                return
            }

            this.restore_loading = true
            this.restore_error = ''
            this.restore_result = null

            try {
                const response = await axios.post(
                    `/api/admin/teaching/backups/${this.selected_preview_backup.id}/restore`,
                    this.sanitizedRestoreSelection(),
                )
                this.restore_result = response.data?.data || null

                const previewResponse = await axios.get(`/api/admin/teaching/backups/${this.selected_preview_backup.id}/preview`)
                this.selected_preview = previewResponse.data?.data || null
                this.initializeRestoreSelection()
                await this.loadRestoreRuns()
            } catch (error) {
                this.restore_error = error?.response?.data?.message || 'Wiederherstellung konnte nicht durchgeführt werden.'
            } finally {
                this.restore_loading = false
            }
        },

        openFullRestoreDialog() {
            if (!this.selected_preview_backup?.id || this.full_restore_loading) {
                return
            }

            this.full_restore_confirm_open = true
        },

        closeFullRestoreDialog() {
            if (this.full_restore_loading) {
                return
            }

            this.full_restore_confirm_open = false
        },

        async restoreFull() {
            if (!this.selected_preview_backup?.id) {
                return
            }

            this.full_restore_loading = true
            this.restore_error = ''
            this.restore_result = null

            try {
                const response = await axios.post(`/api/admin/teaching/backups/${this.selected_preview_backup.id}/restore-full`)
                this.restore_result = response.data?.data || null

                const previewResponse = await axios.get(`/api/admin/teaching/backups/${this.selected_preview_backup.id}/preview`)
                this.selected_preview = previewResponse.data?.data || null
                this.initializeRestoreSelection()
                await this.loadBackups()
            } catch (error) {
                this.restore_error = error?.response?.data?.message || 'Vollständige Wiederherstellung konnte nicht durchgeführt werden.'
            } finally {
                this.full_restore_loading = false
                this.full_restore_confirm_open = false
            }
        },

        sanitizedRestoreSelection() {
            const selectable = this.restoreSelectableValues()

            return {
                courses: this.restore_selection.courses.filter((id) => selectable.courses.includes(id)),
                curricula: this.restore_selection.curricula.filter((id) => selectable.curricula.includes(id)),
                settings: this.restore_selection.settings.filter((key) => selectable.settings.includes(key)),
                overwrite_existing: this.overwrite_existing,
            }
        },

        async loadRestoreRuns() {
            const response = await axios.get('/api/admin/teaching/backups/restore-runs')
            this.restore_runs = Array.isArray(response.data?.data) ? response.data.data : []
        },

        initializeRestoreSelection() {
            const selectable = this.restoreSelectableValues()

            this.restore_selection = {
                courses: selectable.courses,
                curricula: selectable.curricula,
                settings: selectable.settings.filter((key) => {
                    const section = this.previewSettingSections(this.selected_preview).find((item) => item.key === key)

                    return ['missing_current', 'different'].includes(section?.status)
                }),
            }
        },

        restoreSelectableValues() {
            return {
                courses: Array.isArray(this.selected_preview?.courses)
                    ? this.selected_preview.courses.filter((course) => this.isCourseRestoreSelectable(course)).map((course) => course.id)
                    : [],
                curricula: Array.isArray(this.selected_preview?.curricula)
                    ? this.selected_preview.curricula.filter((curriculum) => this.isCurriculumRestoreSelectable(curriculum)).map((curriculum) => curriculum.id)
                    : [],
                settings: this.previewSettingSections(this.selected_preview)
                    .filter((section) => this.isSettingRestoreSelectable(section))
                    .map((section) => section.key),
            }
        },

        setAllRestoreSelected(value) {
            const selectable = value ? this.restoreSelectableValues() : { courses: [], curricula: [], settings: [] }

            this.restore_selection = {
                courses: selectable.courses,
                curricula: selectable.curricula,
                settings: selectable.settings,
            }
        },

        toggleRestoreSelection(scope, value, checked) {
            const currentValues = Array.isArray(this.restore_selection[scope]) ? this.restore_selection[scope] : []
            const nextValues = checked
                ? [...currentValues, value]
                : currentValues.filter((item) => item !== value)

            this.restore_selection = {
                ...this.restore_selection,
                [scope]: [...new Set(nextValues)],
            }
        },

        isRestoreSelected(scope, value, item) {
            if (scope === 'settings' && !this.isSettingRestoreSelectable(item)) {
                return false
            }

            if (scope === 'courses' && !this.isCourseRestoreSelectable(item)) {
                return false
            }

            if (scope === 'curricula' && !this.isCurriculumRestoreSelectable(item)) {
                return false
            }

            return Array.isArray(this.restore_selection[scope]) && this.restore_selection[scope].includes(value)
        },

        validationStatus(backup) {
            return backup?.summary?.validation?.status || 'unknown'
        },

        validationLabel(backup) {
            return {
                valid: 'gültig',
                warning: 'Warnung',
                invalid: 'ungültig',
                unknown: 'unbekannt',
            }[this.validationStatus(backup)] || 'unbekannt'
        },

        validationColor(backup) {
            return {
                valid: 'success',
                warning: 'warning',
                invalid: 'error',
                unknown: 'grey',
            }[this.validationStatus(backup)] || 'grey'
        },

        validationIssues(backup) {
            return Array.isArray(backup?.summary?.validation?.issues) ? backup.summary.validation.issues : []
        },

        validationWarnings(backup) {
            return Array.isArray(backup?.summary?.validation?.warnings) ? backup.summary.validation.warnings : []
        },

        previewStatusLabel(preview) {
            return {
                valid: 'gültig',
                warning: 'Warnung',
                invalid: 'ungültig',
                unknown: 'unbekannt',
            }[preview?.validation?.status || 'unknown'] || 'unbekannt'
        },

        previewStatusColor(preview) {
            return {
                valid: 'success',
                warning: 'warning',
                invalid: 'error',
                unknown: 'grey',
            }[preview?.validation?.status || 'unknown'] || 'grey'
        },

        restoreStatusLabel(status) {
            return {
                current_exists: 'aktuell vorhanden',
                different: 'abweichend',
                missing_current: 'fehlt aktuell',
                in_backup: 'im Backup',
                exists: 'aktuell vorhanden',
                missing: 'fehlt aktuell',
            }[status] || 'unbekannt'
        },

        restoreStatusColor(status) {
            return {
                current_exists: 'success',
                different: 'warning',
                missing_current: 'error',
                in_backup: 'info',
                exists: 'success',
                missing: 'error',
            }[status] || 'grey'
        },

        classesLabel(classes) {
            return Array.isArray(classes) && classes.length ? classes.join(', ') : '-'
        },

        isCourseRestoreSelectable(course) {
            return course?.status === 'missing_current' || (this.overwrite_existing && course?.status === 'current_exists')
        },

        isCurriculumRestoreSelectable(curriculum) {
            return curriculum?.status === 'missing_current' || (this.overwrite_existing && ['current_exists', 'different'].includes(curriculum?.status))
        },

        isSettingRestoreSelectable(section) {
            const count = Number(section?.count || 0)
            const secondaryCount = Number(section?.secondary_count || 0)

            return ['missing_current', 'different'].includes(section?.status) && count + secondaryCount > 0
        },

        restoreResultLabel(result) {
            if (result?.restored === true && result?.counts) {
                return `Vollständig wiederhergestellt: ${this.fullRestoreCount(result.counts)} Datensätze.`
            }

            const restoredCourses = result?.restored?.courses?.length || 0
            const restoredCurricula = result?.restored?.curricula?.length || 0
            const restoredSettings = result?.restored?.settings?.length || 0

            return `Wiederhergestellt: ${restoredCourses} Kurse, ${restoredCurricula} Curricula, ${restoredSettings} Einstellungsbereiche.`
        },

        fullRestoreCount(counts) {
            return Object.entries(counts || {}).reduce((total, [key, count]) => {
                if (key === 'users_matched_by_email') {
                    return total
                }

                return total + Number(count || 0)
            }, 0)
        },

        fullRestoreUserReport(result) {
            if (result?.restored !== true || !result?.user_reconciliation) {
                return ''
            }

            const matchedByEmail = result.user_reconciliation.matched_by_email?.length || 0
            const createdPlaceholders = result.user_reconciliation.created_placeholders?.length || 0
            const parts = []

            if (matchedByEmail > 0) {
                parts.push(`${matchedByEmail} Benutzer:innen per E-Mail zugeordnet`)
            }

            if (createdPlaceholders > 0) {
                parts.push(`${createdPlaceholders} Benutzer:innen als inaktive Platzhalter angelegt`)
            }

            return parts.length ? `${parts.join(', ')}.` : ''
        },

        restoreReportEntries(result) {
            if (result?.restored === true && result?.counts) {
                return Object.entries(result.counts)
                    .filter(([, count]) => Number(count || 0) > 0)
                    .map(([key, count]) => ({
                        key,
                        label: this.tableCountLabel(key),
                        count,
                    }))
            }

            return [
                { key: 'courses', label: 'Kurse', count: result?.restored?.courses?.length || 0 },
                { key: 'curricula', label: 'Curricula', count: result?.restored?.curricula?.length || 0 },
                { key: 'settings', label: 'Einstellungsbereiche', count: result?.restored?.settings?.length || 0 },
                { key: 'skipped_courses', label: 'Übersprungene Kurse', count: result?.skipped?.courses?.length || 0 },
                { key: 'skipped_curricula', label: 'Übersprungene Curricula', count: result?.skipped?.curricula?.length || 0 },
                { key: 'skipped_settings', label: 'Übersprungene Einstellungsbereiche', count: result?.skipped?.settings?.length || 0 },
            ].filter((entry) => Number(entry.count || 0) > 0)
        },

        restoreUserReconciliationEntries(result) {
            const matched = result?.user_reconciliation?.matched_by_email || []
            const placeholders = result?.user_reconciliation?.created_placeholders || []

            return [
                ...matched.map((entry) => ({ ...entry, type: 'per E-Mail zugeordnet' })),
                ...placeholders.map((entry) => ({ ...entry, type: 'Platzhalter angelegt' })),
            ]
        },

        restoreRunTypeLabel(type) {
            return type === 'full' ? 'Vollständig' : 'Teilweise'
        },

        restoreRunStatusLabel(status) {
            return {
                pending: 'ausstehend',
                running: 'läuft',
                completed: 'abgeschlossen',
                failed: 'fehlgeschlagen',
            }[status] || status
        },

        restoreRunStatusColor(status) {
            return {
                pending: 'secondary',
                running: 'info',
                completed: 'success',
                failed: 'error',
            }[status] || 'secondary'
        },

        previewSettingSections(preview) {
            if (Array.isArray(preview?.setting_sections)) {
                return preview.setting_sections
            }

            return [
                { key: 'grading_schemas', label: 'Benotungsschemas', count: preview?.settings?.schemas ?? 0, unit: 'Schemata', status: 'in_backup' },
                { key: 'school_holidays', label: 'Ferien / freie Tage', count: preview?.settings?.holidays ?? 0, unit: 'Tage', status: 'in_backup' },
                { key: 'school_hours', label: 'Schulstunden', count: preview?.settings?.school_hours ?? 0, unit: 'Stunden', status: 'in_backup' },
            ]
        },

        sectionCountLabel(section) {
            const count = section?.count ?? 0
            const unit = section?.unit || 'Einträge'
            const primary = `${count} ${unit}`

            if (section?.secondary_count == null) {
                return primary
            }

            return `${primary} / ${section.secondary_count} ${section.secondary_unit || 'Einträge'}`
        },

        settingSectionStatusLabel(section) {
            return this.restoreStatusLabel(section?.status || 'in_backup')
        },

        settingSectionStatusColor(section) {
            return this.restoreStatusColor(section?.status || 'in_backup')
        },

        tableCountEntries(backup) {
            const counts = backup?.summary?.table_counts || {}

            return Object.entries(counts)
                .map(([key, count]) => ({
                    key,
                    label: this.tableCountLabel(key),
                    count,
                }))
                .sort((a, b) => a.label.localeCompare(b.label, 'de'))
        },

        tableCountLabel(key) {
            return {
                schools: 'Schule',
                schoolyears: 'Schuljahr',
                school_tools: 'Schul-Tool-Einstellungen',
                users: 'Unterrichtsbezogene Benutzer:innen',
                teaching_courses: 'Kurse',
                teaching_course_students: 'Kurs-Schüler:innen',
                teaching_course_dates: 'Termine',
                teaching_course_date_materials: 'Termin-Materialien',
                teaching_course_date_material_attachments: 'Termin-Materialdateien',
                teaching_course_works: 'Arbeiten',
                teaching_course_work_group_students: 'Gruppenarbeiten',
                teaching_course_student_entries: 'Leistungs-Einträge',
                teaching_course_behaviour_entries: 'Verhaltens-Einträge',
                teaching_course_student_category_evaluations: 'Kategorie-Auswertungen',
                teaching_curricula: 'Curricula',
                teaching_curriculum_documents: 'Curriculum-Dokumente',
                teaching_imported_curricula: 'Importierte Curricula',
                teaching_schemas: 'Noten-Schemata',
                teaching_holidays: 'Unterrichtsfreie Tage',
                teaching_school_hours: 'Schulstunden',
                import116: 'Import116-Schüler:innen',
                import116_runs: 'Import116-Läufe',
                import116_run_changes: 'Import116-Änderungen',
                user_groups: 'Kurs-Gruppen',
                user_group_members: 'Kurs-Gruppenmitglieder',
            }[key] || key
        },

        formatDateTime(value) {
            if (!value) {
                return '-'
            }

            return new Date(value).toLocaleString('de-DE', {
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
.teaching-data-backup-col {
    max-width: 1100px;
}

.teaching-data-backup-shell {
    overflow: hidden;
    border: 1px solid rgba(16, 38, 58, 0.12);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.92);
}

.teaching-data-backup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px;
}

.teaching-data-backup-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.teaching-data-backup-empty,
.teaching-data-backup-state {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px;
}

.teaching-data-backup-state {
    justify-content: center;
    min-height: 96px;
}

.teaching-data-backup-table {
    border-top: 1px solid rgba(16, 38, 58, 0.1);
}

.teaching-data-backup-file {
    max-width: 420px;
    overflow-wrap: anywhere;
}

.teaching-data-backup-detail-grid {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.teaching-data-backup-counts {
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
}

.teaching-data-backup-restore-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 10px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(16, 38, 58, 0.03);
}

.teaching-data-backup-select-col {
    width: 42px;
}

@media (max-width: 720px) {
    .teaching-data-backup-header {
        align-items: stretch;
        flex-direction: column;
    }

    .teaching-data-backup-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .teaching-data-backup-detail-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
