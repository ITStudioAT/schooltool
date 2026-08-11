<template>
    <Overview v-if="subAction === 'overview'" />

    <v-col v-else cols="12" md="6" lg="7" xl="4">
        <v-card v-if="subAction === 'imports' && !activeImportPage" rounded="xl" class="st-dummy-card">
            <v-card-title class="d-flex align-center ga-2 pt-4 px-4">
                <v-icon color="primary" size="22">mdi-import</v-icon>
                Importe
            </v-card-title>
            <v-card-text class="px-4 pb-4">
                <div class="st-import-buttons">
                    <v-btn
                        v-for="button in importButtons"
                        :key="button.label"
                        block
                        class="st-import-button"
                        color="primary"
                        size="x-large"
                        variant="tonal"
                        :prepend-icon="button.icon"
                        @click="openImportPage(button.key)">
                        <span class="st-import-button__content">
                            <span class="st-import-button__label">{{ button.label }}</span>
                            <span class="st-import-button__meta">{{ button.meta }}</span>
                            <span v-if="button.detail" class="st-import-button__detail">{{ button.detail }}</span>
                        </span>
                    </v-btn>
                </div>
            </v-card-text>
        </v-card>

        <template v-else-if="subAction === 'imports' && activeImportPage === 'import116'">
            <v-card rounded="xl" class="st-dummy-card">
                <v-card-title class="st-import-page-title pt-4 px-4">
                    <v-icon color="primary" size="22" icon="mdi-account-school-outline" />
                    <span class="st-import-page-title__content">
                        <span class="st-import-page-title__label">Sokrates 116</span>
                        <span class="st-import-page-title__meta">Schüler- und Elterndaten synchronisieren</span>
                    </span>
                    <v-btn
                        size="small"
                        variant="tonal"
                        color="primary"
                        prepend-icon="mdi-arrow-left"
                        class="st-import-back-button"
                        @click="closeImportPage">
                        Zurück
                    </v-btn>
                </v-card-title>
                <v-card-text class="px-4 pb-4">
                    <v-alert type="info" variant="tonal" class="mb-3">
                        <div class="mb-2">Hier können die Schüler- und Elterndaten aus Sokrates-Bund übernommen werden.</div>
                        <div class="text-decoration-underline">Folgende Datei ist zu importieren:</div>
                        <div>Sokrates Bund ➜ Auswertungen ➜ Dynamische Suche ➜ Name der Abfrage: 116 ➜</div>
                        <div>Alle auswählen > Ausführen ➜ Exportieren (XLSX)</div>
                    </v-alert>

                    <div class="text-caption">Es muss sich um eine Excel-Datei (*.xlsx) handeln.</div>
                    <div v-if="import116LastImportDisplay" class="text-caption">
                        Letzter Import: {{ import116LastImportDisplay }}
                    </div>

                    <div v-if="!import116UploadFinished">
                        <FileUpload
                            path="/api/admin/students-timetables/import116-upload/116"
                            fileLabel
                            :allowedFileTypes="['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']"
                            :refreshFilePond="import116RefreshFilePond"
                            class="mt-2"
                            @fileUploadFinished="import116FileUploadFinished"
                            @uploadStart="import116OnUploadStart"
                            @error="import116UploadError" />
                    </div>

                    <v-alert v-if="import116Importing" type="info" variant="tonal" class="mt-2">
                        <div class="d-flex flex-row align-center ga-2">
                            <v-progress-circular indeterminate size="26" width="3" color="primary" />
                            <div>Datei hochgeladen. Die Verarbeitung läuft – Sie erhalten eine Meldung, sobald der Import abgeschlossen ist.</div>
                        </div>
                    </v-alert>
                    <v-alert v-if="import116UploadHasError" type="error" variant="tonal" class="mt-2">Upload fehlgeschlagen.</v-alert>
                    <v-btn v-if="import116UploadFinished || import116UploadHasError" color="warning" variant="flat" class="mt-2" @click="import116ResetUpload">Neu hochladen</v-btn>

                    <v-divider class="my-4" />

                    <div class="d-flex flex-row align-center justify-space-between ga-2">
                        <div class="text-subtitle-2">Importe</div>
                        <v-btn size="small" variant="flat" color="secondary" :loading="import116LoadingRuns" @click="import116LoadRuns">Aktualisieren</v-btn>
                    </div>

                    <v-alert v-if="import116RunActionMessage" type="success" class="mt-2" density="compact">{{ import116RunActionMessage }}</v-alert>
                    <v-alert v-if="import116RunActionError" type="error" class="mt-2" density="compact">{{ import116RunActionError }}</v-alert>
                    <v-alert v-if="import116RunTrackingError" type="warning" class="mt-2" density="compact">{{ import116RunTrackingError }}</v-alert>

                    <div v-if="!import116RunTrackingError" class="mt-2">
                        <div class="d-flex flex-row align-end ga-2">
                            <v-btn
                                color="warning"
                                variant="flat"
                                :disabled="!import116CanRestoreSelection"
                                :loading="import116ResettingRuns"
                                @click="import116ResetRecentRuns">
                                Import zurücksetzen
                            </v-btn>
                        </div>
                        <div class="text-caption mt-1">
                            Maximal {{ import116RunsMeta.reset_max_runs || 0 }} Importdateien gespeichert. Verfügbar: {{ import116RunsMeta.available_reset_runs || 0 }}
                        </div>
                        <div class="text-caption" v-if="import116SelectedRestoreTargetId">
                            Ziel: Import #{{ import116SelectedRestoreTargetId }}
                        </div>
                        <div class="text-caption" v-else>
                            Klicken Sie auf einen Import in der Liste, um ihn als Ziel für das Zurücksetzen auszuwählen.
                        </div>
                    </div>

                    <div v-if="!import116LoadingRuns && !import116RunTrackingError && import116Runs.length === 0" class="text-caption mt-3">
                        Noch keine Importe protokolliert.
                    </div>

                    <div class="mt-3 d-flex flex-column ga-2" v-if="import116Runs.length > 0" style="max-height: 62vh; overflow: auto; padding-right: 2px;">
                        <v-card
                            v-for="run in import116Runs"
                            :key="run.id"
                            variant="outlined"
                            @click="import116SelectRestoreTarget(run)">
                            <v-card-text class="pa-3">
                                <div class="d-flex flex-row flex-wrap align-center justify-space-between ga-2">
                                    <div>
                                        <div class="text-subtitle-2 d-flex align-center ga-2">
                                            <span>Import #{{ run.id }}</span>
                                            <v-chip v-if="import116IsSelectedRestoreTarget(run)" size="x-small" color="warning" variant="flat">Ziel</v-chip>
                                            <v-chip
                                                v-if="import116CanSelectAsRestoreTarget(run)"
                                                size="x-small"
                                                color="success"
                                                variant="flat">
                                                zurücksetzbar
                                            </v-chip>
                                            <v-chip
                                                v-else-if="import116IsActiveImport(run)"
                                                size="x-small"
                                                color="warning"
                                                variant="flat">
                                                nicht zurücksetzbar
                                            </v-chip>
                                        </div>
                                        <div class="text-caption">
                                            {{ import116FormatDateTime(run.finished_at || run.started_at) }}
                                            <span v-if="run.undone_at"> | zurückgesetzt</span>
                                            <span v-else-if="run.status"> | {{ run.status }}</span>
                                        </div>
                                        <div class="text-caption" v-if="run.source_name">
                                            Datei: {{ run.source_name }}
                                        </div>
                                    </div>
                                    <div class="d-flex flex-row flex-wrap ga-1">
                                        <v-chip size="x-small" color="success" variant="tonal">+ {{ run.counts?.inserted || 0 }}</v-chip>
                                        <v-chip size="x-small" color="info" variant="tonal">~ {{ run.counts?.updated || 0 }}</v-chip>
                                        <v-chip size="x-small" color="error" variant="tonal">- {{ run.counts?.deleted || 0 }}</v-chip>
                                    </div>
                                </div>

                                <div class="text-caption mt-2">
                                    Zeilen: {{ run.counts?.processed_rows || 0 }}, Änderungen gesamt: {{ run.counts?.changes_total || 0 }}
                                </div>

                                <div class="d-flex flex-row ga-2 mt-2">
                                    <v-btn size="small" variant="text" @click.stop="import116ToggleRunDetails(run.id)">
                                        {{ import116ExpandedRunIds[run.id] ? 'Details ausblenden' : 'Details anzeigen' }}
                                    </v-btn>
                                    <v-btn
                                        size="small"
                                        color="error"
                                        variant="text"
                                        :loading="import116DeletingImportId === run.id"
                                        @click.stop="import116DeleteImport(run)">
                                        Import löschen
                                    </v-btn>
                                </div>

                                <v-progress-linear v-if="import116LoadingRunId === run.id" indeterminate class="mt-2" />

                                <div v-if="import116ExpandedRunIds[run.id]" class="mt-2">
                                    <div v-if="!import116RunDetails[run.id]" class="text-caption">Details werden geladen ...</div>
                                    <div v-else class="d-flex flex-column ga-3">
                                        <div v-for="type in ['inserted', 'updated', 'deleted']" :key="`${run.id}-${type}`">
                                            <div class="d-flex align-center justify-space-between ga-2">
                                                <div class="text-body-2 font-weight-medium">
                                                    {{ import116ChangeTypeLabel(type) }} ({{ import116RunDetails[run.id]?.changes?.[type]?.length || 0 }})
                                                </div>
                                                <v-btn
                                                    size="x-small"
                                                    variant="text"
                                                    @click.stop="import116ToggleChangeGroup(run.id, type)">
                                                    {{ import116ExpandedChangeGroups[`${run.id}:${type}`] ? 'Schließen' : 'Öffnen' }}
                                                </v-btn>
                                            </div>
                                            <template v-if="import116ExpandedChangeGroups[`${run.id}:${type}`]">
                                                <v-list density="compact" class="py-0" v-if="(import116RunDetails[run.id]?.changes?.[type] || []).length > 0">
                                                    <v-list-item v-for="item in import116RunDetails[run.id].changes[type]" :key="`${run.id}-${type}-${item.id}`" class="px-0">
                                                        <v-list-item-title>
                                                            {{ item.name || '-' }}
                                                            <span class="text-caption">({{ item.student_code }}<span v-if="item.class">, {{ item.class }}</span>)</span>
                                                        </v-list-item-title>
                                                    </v-list-item>
                                                </v-list>
                                                <div v-else class="text-caption">Keine</div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </div>
                </v-card-text>
            </v-card>
        </template>

        <template v-else-if="subAction === 'imports' && activeImportButton && activeImportSubPage !== 'import'">
            <v-card rounded="xl" class="st-dummy-card">
                <v-card-title class="st-import-page-title pt-4 px-4">
                    <v-icon color="primary" size="22" :icon="activeImportButton.icon" />
                    <span class="st-import-page-title__content">
                        <span class="st-import-page-title__label">{{ activeImportButton.label }}</span>
                        <span class="st-import-page-title__meta">{{ activeImportButton.meta }}</span>
                        <span v-if="activeImportButton.detail" class="st-import-page-title__detail">
                            {{ activeImportButton.detail }}
                        </span>
                    </span>
                    <v-btn
                        size="small"
                        variant="tonal"
                        color="primary"
                        prepend-icon="mdi-arrow-left"
                        class="st-import-back-button"
                        @click="closeImportPage">
                        Zurück
                    </v-btn>
                </v-card-title>
                <v-card-text class="px-4 pb-4">
                <section
                    v-if="activeImportPage === 'stundenplan' && mainDataset"
                    class="st-main-dataset-summary mb-4">
                    <div class="st-main-dataset-summary__header">
                        <v-icon icon="mdi-database-outline" color="primary" size="20" />
                        <div class="st-main-dataset-summary__title">
                            <div class="text-caption text-medium-emphasis">Hauptdatenbestand</div>
                            <div class="font-weight-bold">{{ mainDataset.name || 'Aktiver Stundenplan' }}</div>
                        </div>
                        <v-chip size="small" color="primary" variant="tonal">
                            {{ mainDataset.table || 'student_timetable_entries' }}
                        </v-chip>
                    </div>

                    <div class="st-main-dataset-summary__grid">
                        <div
                            v-for="item in mainDatasetSummaryItems"
                            :key="item.label"
                            class="st-main-dataset-summary__item">
                            <span>{{ item.label }}</span>
                            <strong>{{ item.value }}</strong>
                        </div>
                    </div>
                </section>

                <section
                    v-if="activeImportPage === 'stundenplan' && activeDatasetCourses.length"
                    class="st-course-summary mb-4">
                    <v-expansion-panels variant="accordion">
                        <v-expansion-panel elevation="0" class="st-course-summary__panel">
                            <v-expansion-panel-title>
                                <div class="st-course-summary__title">
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
                    </v-expansion-panels>
                </section>

                <section
                    v-if="activeImportPage === 'stundenplan' && activeDatasetSingleDateCourses.length"
                    class="st-single-date-summary mb-4">
                    <v-expansion-panels variant="accordion">
                        <v-expansion-panel elevation="0" class="st-single-date-summary__panel">
                            <v-expansion-panel-title>
                                <div class="st-single-date-summary__title">
                                    <v-icon icon="mdi-calendar-star-outline" color="warning" size="18" />
                                    <span class="font-weight-medium">Einzeltermine</span>
                                    <v-chip size="x-small" color="warning" variant="tonal">
                                        {{ activeDatasetSingleDateAppointmentsCount }}
                                    </v-chip>
                                    <LoadingAnimation
                                        v-if="singleDateActivationSaveInProgress"
                                        class="st-single-date-saving-dots" />
                                </div>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <v-alert v-if="singleDateActivationError" type="error" variant="tonal" class="mb-3">
                                    {{ singleDateActivationError }}
                                </v-alert>
                                <v-table density="compact">
                                    <caption v-if="singleDateActivationSaveInProgress" class="st-single-date-save-caption">
                                        Änderungen werden im Hintergrund gespeichert.
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th class="st-single-date-select-col">
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
                                            <tr class="st-single-date-course-row">
                                                <td class="st-single-date-select-col">
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
                                                    'st-single-date-appointment-row--inactive': !singleDateAppointmentActive(courseItem, appointment),
                                                }">
                                                <td class="st-single-date-select-col">
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
                </section>

                <section
                    v-if="activeImportPage === 'anrechnungen' && activeRecognitionDataset"
                    class="st-main-dataset-summary mb-4">
                    <div class="st-main-dataset-summary__header">
                        <v-icon icon="mdi-database-outline" color="primary" size="20" />
                        <div class="st-main-dataset-summary__title">
                            <div class="text-caption text-medium-emphasis">Hauptdatenbestand</div>
                            <div class="font-weight-bold">{{ activeRecognitionDataset.name || 'Aktive Anrechnungen' }}</div>
                        </div>
                        <v-chip size="small" color="primary" variant="tonal">
                            {{ activeRecognitionDataset.table || 'student_timetable_recognition_rows' }}
                        </v-chip>
                    </div>

                    <div class="st-main-dataset-summary__grid">
                        <div
                            v-for="item in recognitionDatasetSummaryItems"
                            :key="item.label"
                            class="st-main-dataset-summary__item">
                            <span>{{ item.label }}</span>
                            <strong>{{ item.value }}</strong>
                        </div>
                    </div>
                </section>

                <section
                    v-if="activeImportPage === 'anrechnungen' && activeRecognitionSubjectGradeCounts.length"
                    class="st-course-summary mb-4">
                    <v-expansion-panels variant="accordion">
                        <v-expansion-panel elevation="0" class="st-course-summary__panel">
                            <v-expansion-panel-title>
                                <div class="st-course-summary__title">
                                    <v-icon icon="mdi-book-open-variant-outline" color="primary" size="18" />
                                    <span class="font-weight-medium">Fächer</span>
                                    <v-chip size="x-small" color="primary" variant="tonal">
                                        {{ activeRecognitionSubjectGradeCounts.length }}
                                    </v-chip>
                                </div>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <v-table density="compact" class="st-import-history-subject-table">
                                    <colgroup>
                                        <col class="st-import-history-label-col" />
                                        <col class="st-import-history-count-col" />
                                        <col class="st-import-history-count-col" />
                                        <col class="st-import-history-count-col" />
                                        <col class="st-import-history-count-col" />
                                        <col class="st-import-history-count-col" />
                                        <col class="st-import-history-count-col" />
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Fach</th>
                                            <th class="text-right">1-4</th>
                                            <th class="text-right">5</th>
                                            <th class="text-right">N</th>
                                            <th class="text-right st-import-history-teacher-divider st-import-history-teacher-total-cell">
                                                <v-icon icon="mdi-sigma" size="14" title="Summe" />
                                            </th>
                                            <th class="text-right st-import-history-teacher-divider">A</th>
                                            <th class="text-right">B</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template
                                            v-for="subjectItem in activeRecognitionSubjectGradeCounts"
                                            :key="subjectItem.subject">
                                            <tr>
                                                <td>{{ subjectItem.subject }}</td>
                                                <td class="text-right">{{ subjectItem.one_to_four_count || 0 }}</td>
                                                <td class="text-right">{{ subjectItem.five_count || 0 }}</td>
                                                <td class="text-right">{{ subjectItem.n_count || 0 }}</td>
                                                <td class="text-right st-import-history-subject-total-cell">
                                                    {{ recognitionSubjectCountedTotal(subjectItem) }}
                                                </td>
                                                <td class="text-right">{{ subjectItem.other_count || 0 }}</td>
                                                <td class="text-right">{{ subjectItem.b_count || 0 }}</td>
                                            </tr>
                                            <tr class="st-import-history-subject-percent-row">
                                                <td></td>
                                                <td class="text-right">
                                                    {{ recognitionSubjectPercentage(subjectItem.one_to_four_count, recognitionSubjectCountedTotal(subjectItem)) }}
                                                </td>
                                                <td class="text-right">
                                                    {{ recognitionSubjectPercentage(subjectItem.five_count, recognitionSubjectCountedTotal(subjectItem)) }}
                                                </td>
                                                <td class="text-right">
                                                    {{ recognitionSubjectPercentage(subjectItem.n_count, recognitionSubjectCountedTotal(subjectItem)) }}
                                                </td>
                                                <td class="text-right st-import-history-subject-total-cell"></td>
                                                <td class="text-right"></td>
                                                <td class="text-right"></td>
                                            </tr>
                                        </template>
                                        <tr class="st-import-history-subject-sum-row">
                                            <td>Summe</td>
                                            <td class="text-right">{{ activeRecognitionGradeCounts?.one_to_four || 0 }}</td>
                                            <td class="text-right">{{ activeRecognitionGradeCounts?.five || 0 }}</td>
                                            <td class="text-right">{{ activeRecognitionGradeCounts?.n || 0 }}</td>
                                            <td class="text-right st-import-history-subject-total-cell">
                                                {{ recognitionGradeCountedTotal(activeRecognitionGradeCounts) }}
                                            </td>
                                            <td class="text-right">{{ activeRecognitionGradeCounts?.other || 0 }}</td>
                                            <td class="text-right">{{ activeRecognitionGradeCounts?.b || 0 }}</td>
                                        </tr>
                                        <tr class="st-import-history-subject-percent-row st-import-history-subject-sum-percent-row">
                                            <td></td>
                                            <td class="text-right">
                                                {{ recognitionSubjectPercentage(activeRecognitionGradeCounts?.one_to_four, recognitionGradeCountedTotal(activeRecognitionGradeCounts)) }}
                                            </td>
                                            <td class="text-right">
                                                {{ recognitionSubjectPercentage(activeRecognitionGradeCounts?.five, recognitionGradeCountedTotal(activeRecognitionGradeCounts)) }}
                                            </td>
                                            <td class="text-right">
                                                {{ recognitionSubjectPercentage(activeRecognitionGradeCounts?.n, recognitionGradeCountedTotal(activeRecognitionGradeCounts)) }}
                                            </td>
                                            <td class="text-right st-import-history-subject-total-cell"></td>
                                            <td class="text-right"></td>
                                            <td class="text-right"></td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </section>

                <section
                    v-if="activeImportPage === 'anrechnungen' && activeRecognitionTeacherCodes.length"
                    class="st-course-summary mb-4">
                    <v-expansion-panels variant="accordion">
                        <v-expansion-panel elevation="0" class="st-course-summary__panel">
                            <v-expansion-panel-title>
                                <div class="st-course-summary__title">
                                    <v-icon icon="mdi-account-tie-outline" color="primary" size="18" />
                                    <span class="font-weight-medium">Lehrer</span>
                                    <v-chip size="x-small" color="primary" variant="tonal">
                                        {{ activeRecognitionTeacherCodes.length }}
                                    </v-chip>
                                </div>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <v-table density="compact" class="st-import-history-teacher-table">
                                    <colgroup>
                                        <col class="st-import-history-label-col" />
                                        <col class="st-import-history-count-col" />
                                        <col class="st-import-history-count-col" />
                                        <col class="st-import-history-count-col" />
                                        <col class="st-import-history-count-col" />
                                        <col class="st-import-history-count-col" />
                                        <col class="st-import-history-count-col" />
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Alle Lehrer</th>
                                            <th class="text-right">1-4</th>
                                            <th class="text-right">5</th>
                                            <th class="text-right">N</th>
                                            <th class="text-right">
                                                <v-icon icon="mdi-sigma" size="14" title="Summe" />
                                            </th>
                                            <th class="text-right">A</th>
                                            <th class="text-right">B</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template
                                            v-for="teacherItem in activeRecognitionTeacherCodes"
                                            :key="teacherItem.code">
                                            <tr>
                                                <td>{{ teacherItem.code }}</td>
                                                <td class="text-right">{{ teacherItem.one_to_four_count || 0 }}</td>
                                                <td class="text-right">{{ teacherItem.five_count || 0 }}</td>
                                                <td class="text-right">{{ teacherItem.n_count || 0 }}</td>
                                                <td class="text-right st-import-history-teacher-divider st-import-history-teacher-total-cell">
                                                    {{ recognitionTeacherCountedTotal(teacherItem) }}
                                                </td>
                                                <td class="text-right st-import-history-teacher-divider">{{ teacherItem.other_count || 0 }}</td>
                                                <td class="text-right">{{ teacherItem.b_count || 0 }}</td>
                                            </tr>
                                            <tr class="st-import-history-teacher-percent-row">
                                                <td class="st-import-history-teacher-subjects-cell">
                                                    {{ recognitionTeacherSubjectsLabel(teacherItem) }}
                                                </td>
                                                <td class="text-right">
                                                    {{ recognitionSubjectPercentage(teacherItem.one_to_four_count, recognitionTeacherCountedTotal(teacherItem)) }}
                                                </td>
                                                <td class="text-right">
                                                    {{ recognitionSubjectPercentage(teacherItem.five_count, recognitionTeacherCountedTotal(teacherItem)) }}
                                                </td>
                                                <td class="text-right">
                                                    {{ recognitionSubjectPercentage(teacherItem.n_count, recognitionTeacherCountedTotal(teacherItem)) }}
                                                </td>
                                                <td class="text-right st-import-history-teacher-divider st-import-history-teacher-total-cell"></td>
                                                <td class="text-right st-import-history-teacher-divider"></td>
                                                <td class="text-right"></td>
                                            </tr>
                                        </template>
                                        <tr class="st-import-history-teacher-sum-row">
                                            <td>Summe</td>
                                            <td class="text-right">{{ recognitionTeacherOneToFourTotal(activeRecognitionTeacherCodes) }}</td>
                                            <td class="text-right">{{ recognitionTeacherFiveTotal(activeRecognitionTeacherCodes) }}</td>
                                            <td class="text-right">{{ recognitionTeacherNTotal(activeRecognitionTeacherCodes) }}</td>
                                            <td class="text-right st-import-history-teacher-divider st-import-history-teacher-total-cell">
                                                {{ recognitionTeacherTotal(activeRecognitionTeacherCodes) }}
                                            </td>
                                            <td class="text-right st-import-history-teacher-divider">
                                                {{ recognitionTeacherOtherTotal(activeRecognitionTeacherCodes) }}
                                            </td>
                                            <td class="text-right">{{ recognitionTeacherBTotal(activeRecognitionTeacherCodes) }}</td>
                                        </tr>
                                        <tr class="st-import-history-teacher-percent-row st-import-history-teacher-sum-percent-row">
                                            <td></td>
                                            <td class="text-right">
                                                {{ recognitionSubjectPercentage(recognitionTeacherOneToFourTotal(activeRecognitionTeacherCodes), recognitionTeacherTotal(activeRecognitionTeacherCodes)) }}
                                            </td>
                                            <td class="text-right">
                                                {{ recognitionSubjectPercentage(recognitionTeacherFiveTotal(activeRecognitionTeacherCodes), recognitionTeacherTotal(activeRecognitionTeacherCodes)) }}
                                            </td>
                                            <td class="text-right">
                                                {{ recognitionSubjectPercentage(recognitionTeacherNTotal(activeRecognitionTeacherCodes), recognitionTeacherTotal(activeRecognitionTeacherCodes)) }}
                                            </td>
                                            <td class="text-right st-import-history-teacher-divider st-import-history-teacher-total-cell"></td>
                                            <td class="text-right st-import-history-teacher-divider"></td>
                                            <td class="text-right"></td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </section>

                </v-card-text>
            </v-card>

            <v-card
                v-if="activeImportPage === 'stundenplan'"
                rounded="xl"
                class="st-import-file-info-card mt-3">
                <v-card-title class="st-import-file-info-card__title px-4 pt-4">
                    <v-icon icon="mdi-file-document-check-outline" size="20" color="primary" />
                    <span class="font-weight-bold">Benötigte Importdatei</span>
                    <v-btn
                        size="small"
                        variant="tonal"
                        color="primary"
                        prepend-icon="mdi-upload"
                        class="st-import-file-info-card__button"
                        to="/admin/students-timetables/timetable/imports/stundenplan/import">
                        Import
                    </v-btn>
                </v-card-title>
                <v-card-text class="px-4 pb-4">
                    <div class="st-import-file-info-grid">
                        <div class="st-import-file-info-item">
                            <span>Dateityp</span>
                            <strong>TXT-Datei (.txt)</strong>
                        </div>
                        <div class="st-import-file-info-item">
                            <span>Quelle</span>
                            <strong>Untis-Export</strong>
                        </div>
                        <div class="st-import-file-info-item">
                            <span>Trennung</span>
                            <strong>Tabstopps / tabulatorgetrennt</strong>
                        </div>
                        <div class="st-import-file-info-item st-import-file-info-item--wide">
                            <span>Inhalt</span>
                            <strong>TT-Einträge mit Stundenplan-Zeilen</strong>
                            <div class="st-import-file-info-note">
                                <div class="st-import-file-info-note__lead">
                                    <v-icon icon="mdi-information-outline" color="warning" size="18" />
                                    <p>Folgende Einträge dürfen in der Untis-Datei enthalten sein:</p>
                                </div>
                                <p>{{ allowedUntisEntryTypes }}</p>
                            </div>
                        </div>
                    </div>
                </v-card-text>
            </v-card>

            <v-card
                v-if="activeImportPage === 'anrechnungen'"
                rounded="xl"
                class="st-import-file-info-card mt-3">
                <v-card-title class="st-import-file-info-card__title px-4 pt-4">
                    <v-icon icon="mdi-file-code-outline" size="20" color="primary" />
                    <span class="font-weight-bold">Benötigte Importdatei</span>
                    <v-btn
                        size="small"
                        variant="tonal"
                        color="primary"
                        prepend-icon="mdi-upload"
                        class="st-import-file-info-card__button"
                        to="/admin/students-timetables/timetable/imports/anrechnungen/import">
                        Import
                    </v-btn>
                </v-card-title>
                <v-card-text class="px-4 pb-4">
                    <div class="st-import-file-info-grid">
                        <div class="st-import-file-info-item">
                            <span>Dateityp</span>
                            <strong>CSV-Datei (.csv)</strong>
                        </div>
                        <div class="st-import-file-info-item">
                            <span>Quelle</span>
                            <strong>Sokrates Bund</strong>
                        </div>
                        <div class="st-import-file-info-item st-import-file-info-item--wide">
                            <span>Inhalt</span>
                            <strong>Studierende, Fächer, Noten</strong>
                        </div>
                    </div>
                </v-card-text>
            </v-card>

            <v-card
                v-if="activeImportPage === 'anrechnungen'"
                rounded="xl"
                class="st-import-history-card mt-3">
                <v-card-title class="st-import-history-card__title px-4 pt-4">
                    <v-icon icon="mdi-history" size="20" color="primary" />
                    <span class="font-weight-bold">Importverlauf</span>
                    <v-chip size="x-small" variant="tonal" color="primary">
                        {{ recognitionImports.length }}
                    </v-chip>
                </v-card-title>
                <v-card-text class="px-4 pb-4">
                    <v-alert v-if="!loadingImportButtonInfo && !recognitionImports.length" type="info" variant="tonal">
                        Noch keine Anrechnungs-Datei importiert.
                    </v-alert>

                    <v-progress-linear v-if="loadingImportButtonInfo" indeterminate color="primary" class="mb-2" />

                    <v-expansion-panels v-if="recognitionImports.length" variant="accordion" multiple>
                        <v-expansion-panel
                            v-for="importItem in recognitionImports"
                            :key="importItem.id || importItem.filename"
                            elevation="0"
                            class="st-import-history-panel">
                            <v-expansion-panel-title>
                                <div class="st-import-history-title">
                                    <v-icon icon="mdi-file-delimited-outline" color="primary" size="18" />
                                    <div class="flex-grow-1">
                                        <div class="font-weight-medium">
                                            {{ importItem.original_filename || importItem.filename }}
                                        </div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ formatDate(importItem.uploaded_at) }} · {{ formatFileSize(importItem.size) }}
                                        </div>
                                    </div>
                                    <v-chip size="x-small" :color="recognitionStatusColor(importItem)" variant="tonal">
                                        {{ recognitionStatusText(importItem) }}
                                    </v-chip>
                                    <v-btn
                                        prepend-icon="mdi-delete-outline"
                                        size="small"
                                        variant="text"
                                        color="error"
                                        class="st-import-history-delete-button"
                                        :disabled="recognitionImportIsProcessing(importItem)"
                                        @click.stop="openRecognitionDeleteDialog(importItem)">
                                        Löschen
                                    </v-btn>
                                </div>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <div class="st-import-history-meta-grid">
                                    <div class="st-import-history-meta-section">Gesamt</div>
                                    <div class="st-import-history-meta-item">
                                        <span>Gesamtzeilen</span>
                                        <strong>{{ importItem.total_rows || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Importierte Zeilen</span>
                                        <strong>{{ importItem.imported_rows || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Übersprungene Zeilen</span>
                                        <strong>{{ importItem.skipped_rows || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Importierte Studierende</span>
                                        <strong>{{ importItem.imported_students_count || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-section">Noten</div>
                                    <div class="st-import-history-meta-item">
                                        <span>Importierte Noten</span>
                                        <strong>{{ importItem.grade_counts?.total || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Noten 1-4</span>
                                        <strong>{{ importItem.grade_counts?.one_to_four || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Noten B</span>
                                        <strong>{{ importItem.grade_counts?.b || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Noten 5</span>
                                        <strong>{{ importItem.grade_counts?.five || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Noten N</span>
                                        <strong>{{ importItem.grade_counts?.n || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Sonstige Noten</span>
                                        <strong>
                                            {{ importItem.grade_counts?.other || 0 }}
                                            <small
                                                v-if="recognitionOtherGradesLabel(importItem.grade_counts?.other_details) !== '-'"
                                                class="st-import-history-inline-detail">
                                                ({{ recognitionOtherGradesLabel(importItem.grade_counts?.other_details) }})
                                            </small>
                                        </strong>
                                    </div>
                                </div>
                                <v-alert
                                    v-if="recognitionImportIsProcessing(importItem)"
                                    type="info"
                                    variant="tonal"
                                    class="mt-3">
                                    {{ importItem.import_message || 'Import wird verarbeitet.' }}
                                </v-alert>
                                <v-alert
                                    v-if="importItem.import_status === 'failed'"
                                    type="error"
                                    variant="tonal"
                                    class="mt-3">
                                    {{ importItem.import_message || 'Import fehlgeschlagen.' }}
                                </v-alert>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </v-card-text>
            </v-card>

            <v-card
                v-if="activeImportPage === 'stundenplan'"
                rounded="xl"
                class="st-import-history-card mt-3">
                <v-card-title class="st-import-history-card__title px-4 pt-4">
                    <v-icon icon="mdi-history" size="20" color="primary" />
                    <span class="font-weight-bold">Importverlauf</span>
                    <v-chip size="x-small" variant="tonal" color="primary">
                        {{ imports.length }}
                    </v-chip>
                </v-card-title>
                <v-card-text class="px-4 pb-4">
                    <v-alert v-if="!loadingImportButtonInfo && !imports.length" type="info" variant="tonal">
                        Noch keine Datei für dieses Schuljahr importiert.
                    </v-alert>

                    <v-progress-linear v-if="loadingImportButtonInfo" indeterminate color="primary" class="mb-2" />

                    <v-expansion-panels v-if="imports.length" variant="accordion" multiple>
                        <v-expansion-panel
                            v-for="importItem in imports"
                            :key="importItem.id"
                            elevation="0"
                            class="st-import-history-panel">
                            <v-expansion-panel-title>
                                <div class="st-import-history-title">
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
                                        class="st-import-history-delete-button"
                                        :disabled="importIsProcessing(importItem)"
                                        @click.stop="openDeleteDialog(importItem)">
                                        Löschen
                                    </v-btn>
                                </div>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <div class="st-import-history-meta-grid mb-3">
                                    <div class="st-import-history-meta-item">
                                        <span>Dateiname</span>
                                        <strong>{{ importItem.original_filename }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Gesamtzeilen</span>
                                        <strong>{{ importItem.total_lines || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Importierte TT-Einträge</span>
                                        <strong>{{ importedTtCount(importItem) }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Nicht importierte TT-Einträge</span>
                                        <strong>{{ importItem.tt_skipped_invalid || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Verschiedene Kurse</span>
                                        <strong>{{ importItem.tt_courses || 0 }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
                                        <span>Zeitraum</span>
                                        <strong>{{ dateRangeLabel(importItem) }}</strong>
                                    </div>
                                    <div class="st-import-history-meta-item">
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
                                            <tr v-if="code === 'TT' && importItem.tt_courses" class="st-tt-sub-row">
                                                <td></td>
                                                <td class="text-caption pl-6">davon verschiedene Kurse</td>
                                                <td class="text-right font-weight-medium text-caption">{{ importItem.tt_courses }}</td>
                                            </tr>
                                            <tr v-if="code === 'TT'" class="st-tt-sub-row">
                                                <td></td>
                                                <td class="text-caption pl-6">davon importiert</td>
                                                <td class="text-right font-weight-medium text-caption">{{ importedTtCount(importItem) }}</td>
                                            </tr>
                                            <tr v-if="code === 'TT' && importItem.tt_skipped_invalid" class="st-tt-sub-row">
                                                <td></td>
                                                <td class="text-caption pl-6">
                                                    nicht importiert: 2. Spalte = 0 oder 8. Spalte ohne Kurs
                                                </td>
                                                <td class="text-right font-weight-medium text-caption">{{ importItem.tt_skipped_invalid }}</td>
                                            </tr>
                                            <tr v-if="code === 'TT' && importItem.tt_first_date" class="st-tt-sub-row">
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
                </v-card-text>
            </v-card>
        </template>

        <v-card
            v-else-if="subAction === 'imports' && activeImportButton && activeImportSubPage === 'import'"
            rounded="xl"
            class="st-dummy-card">
            <v-card-title class="st-import-page-title pt-4 px-4">
                <v-icon color="primary" size="22" :icon="activeImportUploadIcon" />
                <span class="st-import-page-title__content">
                    <span class="st-import-page-title__label">{{ activeImportUploadTitle }}</span>
                    <span class="st-import-page-title__meta">{{ activeImportUploadMeta }}</span>
                    <span class="st-import-page-title__detail">{{ activeImportUploadDetail }}</span>
                </span>
                <v-btn
                    size="small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-arrow-left"
                    class="st-import-back-button"
                    @click="closeImportUploadPage">
                    Zurück
                </v-btn>
            </v-card-title>
            <v-card-text class="px-4 pb-4">
                <v-alert v-if="activeImportPage === 'stundenplan'" type="info" variant="tonal" class="mb-3">
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
                <v-alert v-if="uploadedFilename" type="success" variant="tonal" class="mb-3">
                    {{ activeImportUploadSuccessLabel }} gespeichert: <strong>{{ uploadedFilename }}</strong>
                </v-alert>
                <FileUpload
                    v-if="activeImportUploadVisible"
                    :path="activeImportUploadPath"
                    fileLabel
                    :allowedFileTypes="activeImportAllowedFileTypes"
                    :allowMultiple="activeImportUploadAllowsMultiple"
                    :refreshFilePond="refreshFilePond"
                    @uploadStart="onImportUploadStart"
                    @fileUploadFinished="onUploadFinished"
                    @error="onUploadError" />
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

        <v-dialog v-model="recognitionDeleteDialog" max-width="440" persistent>
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-delete-alert-outline" color="error" />
                    Anrechnungs-Import löschen
                </v-card-title>
                <v-card-text>
                    Soll der Anrechnungs-Import
                    <strong>{{ recognitionDeleteTargetImport?.original_filename }}</strong>
                    gelöscht werden? Dabei werden die importierten Anrechnungs-Zeilen und die gespeicherte CSV-Datei entfernt.
                    Danach kann diese Datei erneut importiert werden.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="deletingRecognitionImport" @click="closeRecognitionDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="deletingRecognitionImport" @click="deleteRecognitionImport">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useValidationRulesSetup } from '@/helpers/rules'

const FileUpload = defineAsyncComponent(() => import('@/pages/components/FileUpload.vue'))
const LoadingAnimation = defineAsyncComponent(() => import('@/pages/components/LoadingAnimation.vue'))
const Overview = defineAsyncComponent(() => import('../overview/Overview.vue'))

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
    name: 'Timetable',
    setup() {
        return useValidationRulesSetup()
    },
    components: { FileUpload, LoadingAnimation, Overview },
    data() {
        return {
            subAction: this.normalizedSubAction(this.$route.params.subsection),
            importPage: this.normalizedImportPage(this.$route.params.detail),
            importSubPage: this.normalizedImportSubPage(this.$route.params.action),
            loadingImportButtonInfo: false,
            importButtonInfo: {
                timetable: null,
                recognitions: null,
                mainDataset: null,
                recognitionDataset: null,
            },
            imports: [],
            recognitionImports: [],
            deleteTargetImport: null,
            deleteDialog: false,
            deleting: false,
            recognitionDeleteTargetImport: null,
            recognitionDeleteDialog: false,
            deletingRecognitionImport: false,
            pollingInterval: null,
            savingSingleDateActivation: false,
            singleDateActivationSaveQueued: false,
            singleDateActivationSaveTimer: null,
            singleDateActivationError: '',
            activeSingleDateAppointmentKeys: [],
            singleDateActivationDatasetSignature: '',
            schoolyearDialog: false,
            schoolyearData: {},
            schoolyearValid: false,
            savingSchoolyear: false,
            uploadedFilename: '',
            refreshFilePond: 0,
            uploadError: '',
            import116UploadFinished: false,
            import116UploadHasError: false,
            import116RefreshFilePond: false,
            import116Importing: false,
            import116OnImportFinished: null,
            import116LastImportAt: null,
            import116Runs: [],
            import116RunsMeta: { reset_max_runs: 0, available_reset_runs: 0, history_limit: 0 },
            import116RunDetails: {},
            import116ExpandedRunIds: {},
            import116ExpandedChangeGroups: {},
            import116LoadingRuns: false,
            import116LoadingRunId: null,
            import116ResettingRuns: false,
            import116DeletingImportId: null,
            import116SelectedRestoreTargetId: null,
            import116RunTrackingError: '',
            import116RunActionMessage: '',
            import116RunActionError: '',
        }
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        configuredRoleNames() {
            return Array.isArray(this.config?.roles) ? this.config.roles : []
        },
        configuredRolesLoaded() {
            return Array.isArray(this.config?.roles)
        },
        canManageTimetableImports() {
            return ['super_admin', 'admin', 'studentstimetables_admin'].some(roleName => this.configuredRoleNames.includes(roleName))
        },
        import116LastImportDisplay() {
            const value = this.import116LastImportAt || this.config?.teaching?.last_import_116_at
            if (!value) return null
            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return value
            return new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
        },
        import116RestorableImports() {
            const active = (this.import116Runs || []).filter((item) => this.import116IsActiveImport(item))
            const max = Number(this.import116RunsMeta?.reset_max_runs || 0)
            return max > 0 ? active.slice(0, max) : []
        },
        import116SelectedRestoreDepth() {
            const targetId = Number(this.import116SelectedRestoreTargetId || 0)
            if (!targetId) return 0
            const idx = this.import116RestorableImports.findIndex((item) => Number(item?.id) === targetId)
            return idx >= 0 ? idx + 1 : 0
        },
        import116CanRestoreSelection() {
            if (this.import116RunTrackingError) return false
            if (!this.import116SelectedRestoreTargetId) return false
            if (this.import116SelectedRestoreDepth <= 0) return false
            return this.import116SelectedRestoreDepth <= Number(this.import116RunsMeta?.reset_max_runs || 0)
        },
        importButtons() {
            return [
                {
                    key: 'stundenplan',
                    label: 'Stundenplan',
                    icon: 'mdi-calendar-import',
                    meta: this.importButtonDateMeta(this.importButtonInfo.timetable?.imported_at),
                    detail: this.timetableImportDetail,
                },
                {
                    key: 'anrechnungen',
                    label: 'Anrechnungen',
                    icon: 'mdi-check-decagram-outline',
                    meta: this.importButtonDateMeta(this.importButtonInfo.recognitions?.uploaded_at),
                    detail: this.recognitionImportDetail,
                },
                {
                    key: 'import116',
                    label: 'Sokrates 116',
                    icon: 'mdi-account-school-outline',
                    meta: this.importButtonDateMeta(this.config?.teaching?.last_import_116_at),
                    detail: 'Schüler- und Elterndaten',
                },
            ]
        },
        activeImportPage() {
            return this.subAction === 'imports' ? this.importPage : ''
        },
        activeImportButton() {
            return this.importButtons.find(button => button.key === this.activeImportPage) || null
        },
        activeImportSubPage() {
            return this.activeImportPage ? this.importSubPage : ''
        },
        activeImportUploadIcon() {
            if (this.activeImportPage === 'import116') return 'mdi-file-table-outline'
            if (this.activeImportPage === 'anrechnungen') return 'mdi-file-delimited-outline'

            return 'mdi-upload'
        },
        activeImportUploadTitle() {
            if (this.activeImportPage === 'import116') return 'XLSX-Datei importieren'
            if (this.activeImportPage === 'anrechnungen') return 'CSV-Datei importieren'

            return 'TXT-Datei importieren'
        },
        activeImportUploadMeta() {
            if (this.activeImportPage === 'import116') return 'Sokrates 116 · Schüler- und Elterndaten'
            if (this.activeImportPage === 'anrechnungen') return 'Anrechnungen · Sokrates Bund'

            return 'Stundenplan · Untis-Export'
        },
        activeImportUploadDetail() {
            if (this.activeImportPage === 'import116') return 'XLSX-Datei (.xlsx), Sokrates Bund Abfrage 116'
            if (this.activeImportPage === 'anrechnungen') return 'CSV-Datei (.csv), mit Studierenden, Fächern und Noten'

            return 'TXT-Datei (.txt), tabulatorgetrennt, mit TT-Einträgen'
        },
        activeImportUploadPath() {
            if (this.activeImportPage === 'import116') {
                return '/api/admin/students-timetables/import116-upload/116'
            }
            if (this.activeImportPage === 'anrechnungen') {
                return '/api/admin/students-timetables/recognitions-csv'
            }

            return '/api/admin/students-timetables/upload'
        },
        activeImportAllowedFileTypes() {
            if (this.activeImportPage === 'import116') return ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            if (this.activeImportPage === 'anrechnungen') return ['text/csv', 'application/csv', 'application/vnd.ms-excel']

            return ['text/plain']
        },
        activeImportUploadVisible() {
            if (this.activeImportPage === 'anrechnungen') return true

            return Boolean(this.semester2StartRaw)
        },
        activeImportUploadAllowsMultiple() {
            return this.activeImportPage === 'anrechnungen'
        },
        activeImportUploadSuccessLabel() {
            if (this.activeImportPage === 'anrechnungen') return 'CSV-Datei'

            return 'TXT-Datei'
        },
        semester2StartRaw() {
            return this.config?.selected_schoolyear?.sem_2_start || ''
        },
        semester2Start() {
            const date = this.semester2StartRaw
            if (!date) return ''

            const dateValue = new Date(date)

            return dateValue.toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        mainDataset() {
            return this.importButtonInfo.mainDataset
        },
        activeRecognitionImport() {
            return this.importButtonInfo.recognitions
        },
        activeRecognitionDataset() {
            return this.importButtonInfo.recognitionDataset
        },
        activeRecognitionGradeCounts() {
            return this.activeRecognitionDataset?.grade_counts || {}
        },
        activeRecognitionSubjectGradeCounts() {
            const subjectGradeCounts = this.activeRecognitionDataset?.subject_grade_counts

            return Array.isArray(subjectGradeCounts) ? subjectGradeCounts : []
        },
        activeRecognitionTeacherCodes() {
            const teacherCodes = this.activeRecognitionDataset?.teacher_codes

            return Array.isArray(teacherCodes) ? teacherCodes : []
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
        mainDatasetSummaryItems() {
            return [
                {
                    label: 'Zeitraum',
                    value: this.datasetDateRangeLabel(this.mainDataset),
                },
                {
                    label: 'Stundenplan-Einträge',
                    value: Number(this.mainDataset?.entries_count || 0),
                },
                {
                    label: 'Verschiedene Kurse',
                    value: Number(this.mainDataset?.courses_count || 0),
                },
                {
                    label: 'Zuletzt geändert',
                    value: this.formatDate(this.mainDataset?.updated_at) || '-',
                },
            ]
        },
        recognitionDatasetSummaryItems() {
            return [
                {
                    label: 'Einträge',
                    value: Number(this.activeRecognitionDataset?.entries_count || 0),
                },
                {
                    label: 'Fächer',
                    value: Number(this.activeRecognitionDataset?.subjects_count || 0),
                },
                {
                    label: 'Lehrer',
                    value: Number(this.activeRecognitionDataset?.teachers_count || 0),
                },
                {
                    label: 'Studierende',
                    value: Number(this.activeRecognitionDataset?.students_count || 0),
                },
            ]
        },
        timetableImportDetail() {
            const importItem = this.importButtonInfo.timetable
            if (!importItem) return ''

            return [
                importItem.original_filename,
                this.importedTtCount(importItem) ? `${this.importedTtCount(importItem)} Einträge` : '',
            ].filter(Boolean).join(' · ')
        },
        recognitionImportDetail() {
            const importItem = this.importButtonInfo.recognitions
            if (!importItem) return ''

            return [
                importItem.original_filename || importItem.filename,
                this.recognitionImportCountLabel(importItem),
            ].filter(Boolean).join(' · ')
        },
        allowedUntisEntryTypes() {
            return Object.keys(SECTION_LABELS).join(', ')
        },
    },
    watch: {
        'config.selected_schoolyear.id'() {
            this.loadImportButtonInfo()
        },
        configuredRoleNames() {
            this.syncRouteStateFromParams()
        },
        '$route.params.subsection'(subsection) {
            this.subAction = this.normalizedSubAction(subsection)
            if (this.redirectRemovedSubjectImportRoute()) {
                return
            }

            if (this.redirectLegacyOverviewRoute()) {
                return
            }

            this.redirectLegacyRobotRoute()
            this.redirectUnauthorizedImportRoute()
            this.loadImportButtonInfo()
        },
        '$route.params.detail'(detail) {
            this.importPage = this.normalizedImportPage(detail)
            this.uploadedFilename = ''
            this.uploadError = ''
            if (this.redirectRemovedSubjectImportRoute()) {
                return
            }

            this.redirectLegacyOverviewRoute()
            this.loadImportButtonInfo()
        },
        '$route.params.action'(action) {
            this.importSubPage = this.normalizedImportSubPage(action)
        },
    },
    mounted() {
        this.syncRouteStateFromParams()
        this.import116OnImportFinished = async (event) => {
            this.import116Importing = false
            this.import116LastImportAt = new Date().toISOString()
            const payload = event?.detail?.data || {}
            if (payload && typeof payload === 'object' && Object.keys(payload).length > 0) {
                this.import116RunActionMessage = `Import abgeschlossen: +${payload.created ?? 0} / ~${payload.updated ?? 0} / -${payload.deleted ?? 0}`
            }
            await this.import116LoadRuns()
        }
        window.addEventListener('import116-finished', this.import116OnImportFinished)
    },
    unmounted() {
        this.clearPolling()
        this.clearSingleDateActivationSaveTimer()
        if (this.import116OnImportFinished) {
            window.removeEventListener('import116-finished', this.import116OnImportFinished)
        }
    },
    methods: {
        normalizedSubAction(subsection) {
            const allowed = this.canManageTimetableImports
                ? ['overview', 'imports']
                : ['overview']

            return allowed.includes(subsection) ? subsection : 'overview'
        },
        normalizedImportPage(detail) {
            if (!this.canManageTimetableImports) return ''

            const allowed = ['stundenplan', 'anrechnungen', 'import116']

            return allowed.includes(detail) ? detail : ''
        },
        normalizedImportSubPage(action) {
            if (!this.canManageTimetableImports) return ''

            const allowed = ['import']

            return allowed.includes(action) ? action : ''
        },
        redirectUnauthorizedImportRoute() {
            if (this.$route.params.subsection !== 'imports' || this.canManageTimetableImports) return
            if (!this.configuredRolesLoaded) return

            this.subAction = 'overview'
            this.importPage = ''
            this.importSubPage = ''
            this.$router.replace({ path: '/admin/students-timetables/timetable/overview' })
        },
        syncRouteStateFromParams() {
            this.subAction = this.normalizedSubAction(this.$route.params.subsection)
            this.importPage = this.normalizedImportPage(this.$route.params.detail)
            this.importSubPage = this.normalizedImportSubPage(this.$route.params.action)

            if (this.redirectRemovedSubjectImportRoute()) {
                return
            }

            if (this.redirectLegacyOverviewRoute()) {
                return
            }

            this.redirectLegacyRobotRoute()
            this.redirectUnauthorizedImportRoute()
            this.loadImportButtonInfo()
        },
        redirectLegacyOverviewRoute() {
            if (
                this.$route.params.section !== 'timetable'
                || this.$route.params.subsection
            ) {
                return false
            }

            this.subAction = 'overview'
            this.importPage = ''
            this.importSubPage = ''
            this.$router.replace({ path: '/admin/students-timetables/timetable/overview' })

            return true
        },
        redirectRemovedSubjectImportRoute() {
            if (
                this.$route.params.subsection !== 'imports'
                || this.$route.params.detail !== 'faecher'
            ) {
                return false
            }

            this.importPage = ''
            this.importSubPage = ''
            this.$router.replace({ path: '/admin/students-timetables/timetable/imports' })

            return true
        },
        redirectLegacyRobotRoute() {
            if (this.$route.params.subsection !== 'robot') return

            this.subAction = 'overview'
            this.importPage = ''
            this.importSubPage = ''
            this.$router.replace({ path: '/admin/students-timetables/timetable/overview/automatic' })
        },
        openImportPage(key) {
            this.importPage = this.normalizedImportPage(key)
            this.importSubPage = ''
            this.uploadedFilename = ''
            this.uploadError = ''
            this.$router.push({ path: `/admin/students-timetables/timetable/imports/${this.importPage}` })
            this.loadImportButtonInfo()
        },
        closeImportPage() {
            this.importPage = ''
            this.importSubPage = ''
            this.$router.push({ path: '/admin/students-timetables/timetable/imports' })
        },
        closeImportUploadPage() {
            this.importSubPage = ''
            this.$router.push({
                path: `/admin/students-timetables/timetable/imports/${this.activeImportPage || 'stundenplan'}`,
                query: this.$route.query,
            })
        },
        onImportUploadStart() {
            this.uploadError = ''
            this.uploadedFilename = ''
        },
        onUploadFinished(file) {
            this.uploadError = ''
            if (this.activeImportPage === 'anrechnungen') {
                this.uploadedFilename = file?.name || 'gespeichert'
                this.refreshFilePond++
                this.loadImportButtonInfo()
                this.closeImportUploadPage()

                return
            }

            this.loadImportButtonInfo()
            this.schedulePolling()
        },
        onUploadError() {
            if (this.activeImportPage === 'anrechnungen') {
                this.uploadedFilename = ''
                this.uploadError = 'Die CSV-Datei konnte nicht gespeichert werden.'
                this.refreshFilePond++

                return
            }

            this.uploadError = 'Der Import konnte nicht durchgeführt werden. Bitte prüfen Sie die TXT-Datei und das Semester-2-Startdatum.'
        },
        openSchoolyearEdit() {
            const schoolyear = this.config?.selected_schoolyear
            if (!schoolyear) return

            this.schoolyearData = { ...schoolyear }
            this.schoolyearDialog = true
        },
        async saveSchoolyear() {
            this.schoolyearValid = false
            await this.$refs.schoolyearForm.validate()
            if (!this.schoolyearValid) return

            this.savingSchoolyear = true
            try {
                const schoolyearStore = useSchoolyearStore()
                if (await schoolyearStore.update(this.schoolyearData)) {
                    const adminStore = useAdminStore()
                    await adminStore.loadConfig()
                    this.schoolyearDialog = false
                }
            } finally {
                this.savingSchoolyear = false
            }
        },
        async loadImportButtonInfo() {
            if (this.subAction !== 'imports' || this.loadingImportButtonInfo) return

            this.loadingImportButtonInfo = true
            try {
                const shouldLoadFullTimetableImports = this.activeImportPage === 'stundenplan'
                const shouldLoadFullRecognitionImports = this.activeImportPage === 'anrechnungen'

                const [timetableResponse, recognitionsResponse] = await Promise.all([
                    axios.get('/api/admin/students-timetables/imports', {
                        params: {
                            page: 1,
                            per_page: shouldLoadFullTimetableImports ? 100 : 1,
                            include_single_date_courses: shouldLoadFullTimetableImports ? 1 : 0,
                            summary: shouldLoadFullTimetableImports ? 0 : 1,
                        },
                    }),
                    axios.get('/api/admin/students-timetables/recognitions-csv', {
                        params: {
                            summary: shouldLoadFullRecognitionImports ? 0 : 1,
                        },
                    }),
                ])
                this.imports = timetableResponse.data?.data || []
                this.recognitionImports = recognitionsResponse.data?.data || []

                this.importButtonInfo = {
                    timetable: this.imports[0] || null,
                    recognitions: this.recognitionImports[0] || null,
                    mainDataset: timetableResponse.data?.main_dataset || null,
                    recognitionDataset: recognitionsResponse.data?.active_dataset || null,
                }
                if (!this.singleDateActivationSaveInProgress) {
                    this.syncSingleDateAppointmentActivation()
                }
                this.updatePolling()
            } catch {
                this.imports = []
                this.importButtonInfo = {
                    timetable: null,
                    recognitions: null,
                    mainDataset: null,
                    recognitionDataset: null,
                }
                this.recognitionImports = []
                this.activeSingleDateAppointmentKeys = []
                this.singleDateActivationDatasetSignature = ''
                this.clearPolling()
            } finally {
                this.loadingImportButtonInfo = false
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
                await this.loadImportButtonInfo()
            } finally {
                this.deleting = false
            }
        },
        openRecognitionDeleteDialog(importItem) {
            this.recognitionDeleteTargetImport = importItem
            this.recognitionDeleteDialog = true
        },
        closeRecognitionDeleteDialog() {
            this.recognitionDeleteDialog = false
            this.recognitionDeleteTargetImport = null
        },
        async deleteRecognitionImport() {
            if (!this.recognitionDeleteTargetImport) return

            this.deletingRecognitionImport = true
            try {
                await axios.delete(`/api/admin/students-timetables/recognitions-csv/${this.recognitionDeleteTargetImport.id}`)
                this.recognitionDeleteTargetImport = null
                this.recognitionDeleteDialog = false
                await this.loadImportButtonInfo()
            } finally {
                this.deletingRecognitionImport = false
            }
        },
        updatePolling() {
            if (
                this.imports.some(importItem => this.importIsProcessing(importItem)) ||
                this.recognitionImports.some(importItem => this.recognitionImportIsProcessing(importItem))
            ) {
                this.schedulePolling()

                return
            }

            this.clearPolling()
        },
        schedulePolling() {
            if (this.pollingInterval) return

            this.pollingInterval = window.setInterval(() => {
                this.loadImportButtonInfo()
            }, 2000)
        },
        clearPolling() {
            if (!this.pollingInterval) return

            window.clearInterval(this.pollingInterval)
            this.pollingInterval = null
        },
        importButtonDateMeta(value) {
            if (this.loadingImportButtonInfo) return 'Importdaten werden geladen'
            if (!value) return 'Noch kein Import'

            return `Letzter Import: ${this.formatDate(value)}`
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
        recognitionImportCountLabel(importItem) {
            const importedRows = Number(importItem?.imported_rows || 0)

            return `${importedRows} Zeilen`
        },
        recognitionImportIsProcessing(importItem) {
            return ['pending', 'running'].includes(importItem?.import_status)
        },
        recognitionStatusText(importItem) {
            if (importItem?.import_status === 'completed') return 'Abgeschlossen'
            if (importItem?.import_status === 'failed') return 'Fehlgeschlagen'
            if (importItem?.import_status === 'running') return 'Läuft'
            if (importItem?.import_status === 'pending') return 'Wartet'

            return 'Unbekannt'
        },
        recognitionStatusColor(importItem) {
            if (importItem?.import_status === 'completed') return 'success'
            if (importItem?.import_status === 'failed') return 'error'
            if (this.recognitionImportIsProcessing(importItem)) return 'info'

            return 'default'
        },
        recognitionOtherGradesLabel(details) {
            if (!Array.isArray(details) || !details.length) return '-'

            return details
                .map(detail => `${detail.note || '-'}: ${Number(detail.count || 0)}`)
                .join(' · ')
        },
        recognitionSubjectPercentage(value, total) {
            const numericTotal = Number(total || 0)

            if (numericTotal <= 0) return '-'

            return `${((Number(value || 0) / numericTotal) * 100).toFixed(1)}%`
        },
        recognitionSubjectCountedTotal(subjectItem) {
            return Number(subjectItem?.one_to_four_count || 0)
                + Number(subjectItem?.five_count || 0)
                + Number(subjectItem?.n_count || 0)
        },
        recognitionGradeCountedTotal(gradeCounts) {
            return Number(gradeCounts?.one_to_four || 0)
                + Number(gradeCounts?.five || 0)
                + Number(gradeCounts?.n || 0)
        },
        recognitionTeacherOneToFourTotal(teacherItems) {
            if (!Array.isArray(teacherItems)) return 0

            return teacherItems.reduce((total, teacherItem) => total + Number(teacherItem?.one_to_four_count || 0), 0)
        },
        recognitionTeacherFiveTotal(teacherItems) {
            if (!Array.isArray(teacherItems)) return 0

            return teacherItems.reduce((total, teacherItem) => total + Number(teacherItem?.five_count || 0), 0)
        },
        recognitionTeacherNTotal(teacherItems) {
            if (!Array.isArray(teacherItems)) return 0

            return teacherItems.reduce((total, teacherItem) => total + Number(teacherItem?.n_count || 0), 0)
        },
        recognitionTeacherCountedTotal(teacherItem) {
            return Number(teacherItem?.one_to_four_count || 0)
                + Number(teacherItem?.five_count || 0)
                + Number(teacherItem?.n_count || 0)
        },
        recognitionTeacherTotal(teacherItems) {
            if (!Array.isArray(teacherItems)) return 0

            return teacherItems.reduce((total, teacherItem) => total + this.recognitionTeacherCountedTotal(teacherItem), 0)
        },
        recognitionTeacherOtherTotal(teacherItems) {
            if (!Array.isArray(teacherItems)) return 0

            return teacherItems.reduce((total, teacherItem) => total + Number(teacherItem?.other_count || 0), 0)
        },
        recognitionTeacherBTotal(teacherItems) {
            if (!Array.isArray(teacherItems)) return 0

            return teacherItems.reduce((total, teacherItem) => total + Number(teacherItem?.b_count || 0), 0)
        },
        recognitionTeacherSubjectsLabel(teacherItem) {
            if (!Array.isArray(teacherItem?.subjects) || teacherItem.subjects.length === 0) return ''

            return teacherItem.subjects.join(', ')
        },
        formatFileSize(size) {
            const bytes = Number(size || 0)
            if (bytes < 1024) return `${bytes} B`

            return `${(bytes / 1024).toFixed(1)} KB`
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
                    this.importButtonInfo.mainDataset = response.data.main_dataset || this.importButtonInfo.mainDataset
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
        formatDate(value) {
            if (!value) return ''

            return new Date(value).toLocaleDateString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            })
        },
        import116IsActiveImport(run) {
            return !!run && run.status === 'completed' && !run.undone_at
        },
        import116CanSelectAsRestoreTarget(run) {
            if (!this.import116IsActiveImport(run)) return false
            return this.import116RestorableImports.some((item) => Number(item?.id) === Number(run?.id))
        },
        async import116LoadRuns() {
            this.import116LoadingRuns = true
            this.import116RunTrackingError = ''
            this.import116RunActionError = ''
            try {
                const response = await axios.get('/api/admin/students-timetables/import116/runs')
                this.import116Runs = response?.data?.data || []
                this.import116RunsMeta = response?.data?.meta || { reset_max_runs: 0, available_reset_runs: 0, history_limit: 0 }
                if (!this.import116RestorableImports.some((item) => Number(item?.id) === Number(this.import116SelectedRestoreTargetId || 0))) {
                    this.import116SelectedRestoreTargetId = null
                }
            } catch (error) {
                this.import116Runs = []
                this.import116RunsMeta = { reset_max_runs: 0, available_reset_runs: 0, history_limit: 0 }
                this.import116RunTrackingError = error?.response?.data?.message || 'Import-Protokolle konnten nicht geladen werden.'
            } finally {
                this.import116LoadingRuns = false
            }
        },
        import116SelectRestoreTarget(run) {
            if (!this.import116CanSelectAsRestoreTarget(run)) return
            const id = Number(run.id || 0)
            if (!id) return
            this.import116SelectedRestoreTargetId = this.import116SelectedRestoreTargetId === id ? null : id
            this.import116RunActionError = ''
            this.import116RunActionMessage = ''
        },
        import116IsSelectedRestoreTarget(run) {
            return Number(this.import116SelectedRestoreTargetId || 0) > 0 && Number(run?.id || 0) === Number(this.import116SelectedRestoreTargetId)
        },
        async import116ToggleRunDetails(runId) {
            const isOpen = !!this.import116ExpandedRunIds[runId]
            if (isOpen) {
                this.import116ExpandedRunIds = { ...this.import116ExpandedRunIds, [runId]: false }
                return
            }

            this.import116ExpandedRunIds = { ...this.import116ExpandedRunIds, [runId]: true }
            if (this.import116RunDetails[runId]) return

            this.import116LoadingRunId = runId
            this.import116RunActionError = ''
            try {
                const response = await axios.get(`/api/admin/students-timetables/import116/runs/${runId}`)
                this.import116RunDetails = {
                    ...this.import116RunDetails,
                    [runId]: response?.data || null,
                }
            } catch (error) {
                this.import116RunActionError = error?.response?.data?.message || 'Import-Details konnten nicht geladen werden.'
                this.import116ExpandedRunIds = { ...this.import116ExpandedRunIds, [runId]: false }
            } finally {
                this.import116LoadingRunId = null
            }
        },
        import116ToggleChangeGroup(runId, type) {
            const key = `${runId}:${type}`
            this.import116ExpandedChangeGroups = {
                ...this.import116ExpandedChangeGroups,
                [key]: !this.import116ExpandedChangeGroups[key],
            }
        },
        async import116ResetRecentRuns() {
            if (!this.import116CanRestoreSelection) return
            this.import116ResettingRuns = true
            this.import116RunActionMessage = ''
            this.import116RunActionError = ''
            try {
                const response = await axios.post('/api/admin/students-timetables/import116/runs/reset', { target_import_id: this.import116SelectedRestoreTargetId })
                this.import116RunActionMessage = response?.data?.message || 'Importe wurden zurückgesetzt.'
                this.import116RunDetails = {}
                this.import116ExpandedRunIds = {}
                this.import116ExpandedChangeGroups = {}
                this.import116SelectedRestoreTargetId = null
                await this.import116LoadRuns()
            } catch (error) {
                this.import116RunActionError = error?.response?.data?.message || 'Import konnte nicht zurückgesetzt werden.'
            } finally {
                this.import116ResettingRuns = false
            }
        },
        async import116DeleteImport(run) {
            const id = Number(run?.id || 0)
            if (!id) return

            this.import116DeletingImportId = id
            this.import116RunActionMessage = ''
            this.import116RunActionError = ''
            try {
                const response = await axios.delete(`/api/admin/students-timetables/import116/runs/${id}`)
                this.import116RunActionMessage = response?.data?.message || `Import #${id} wurde gelöscht.`
                if (Number(this.import116SelectedRestoreTargetId || 0) === id) {
                    this.import116SelectedRestoreTargetId = null
                }
                if (this.import116RunDetails[id]) {
                    const copy = { ...this.import116RunDetails }
                    delete copy[id]
                    this.import116RunDetails = copy
                }
                this.import116ExpandedChangeGroups = Object.fromEntries(
                    Object.entries(this.import116ExpandedChangeGroups).filter(([key]) => !key.startsWith(`${id}:`))
                )
                await this.import116LoadRuns()
            } catch (error) {
                this.import116RunActionError = error?.response?.data?.message || 'Import konnte nicht gelöscht werden.'
            } finally {
                this.import116DeletingImportId = null
            }
        },
        import116FormatDateTime(value) {
            if (!value) return '-'
            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return String(value)
            return new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
        },
        import116ChangeTypeLabel(type) {
            if (type === 'inserted') return 'Eingefügt'
            if (type === 'updated') return 'Aktualisiert'
            if (type === 'deleted') return 'Gelöscht'
            return type
        },
        import116OnUploadStart() {
            this.import116UploadFinished = false
            this.import116UploadHasError = false
            this.import116RunActionMessage = ''
            this.import116RunActionError = ''
            this.import116Importing = true
        },
        import116FileUploadFinished() {
            this.import116UploadFinished = true
        },
        import116UploadError() {
            this.import116UploadHasError = true
            this.import116RefreshFilePond = !this.import116RefreshFilePond
            this.import116Importing = false
        },
        import116ResetUpload() {
            this.import116UploadFinished = false
            this.import116UploadHasError = false
            this.import116RefreshFilePond = !this.import116RefreshFilePond
            this.import116Importing = false
        },
    },
}
</script>

<style scoped>
.st-dummy-card {
    border: 1px solid rgba(37, 99, 235, 0.12);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.st-import-buttons {
    display: grid;
    gap: 12px;
}

.st-import-button {
    min-height: 76px;
    letter-spacing: 0;
    text-transform: none;
    font-weight: 700;
}

.st-import-button :deep(.v-btn__content) {
    width: 100%;
    justify-content: flex-start;
    gap: 10px;
}

.st-import-button__content {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
    text-align: left;
}

.st-import-button__label {
    font-size: 1rem;
}

.st-import-button__meta,
.st-import-button__detail {
    max-width: 100%;
    overflow: hidden;
    color: rgba(30, 64, 175, 0.78);
    font-size: 0.74rem;
    font-weight: 400;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.st-import-page-title {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.st-import-page-title__content {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    line-height: 1.2;
}

.st-import-page-title__label {
    font-size: 1rem;
    font-weight: 700;
}

.st-import-page-title__meta,
.st-import-page-title__detail {
    max-width: 100%;
    overflow: hidden;
    color: rgba(30, 64, 175, 0.78);
    font-size: 0.74rem;
    font-weight: 400;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.st-import-back-button {
    flex: 0 0 auto;
    margin-left: auto;
    text-transform: none;
    letter-spacing: 0;
}

.st-import-file-info-card {
    border: 1px solid rgba(37, 99, 235, 0.12);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.st-import-file-info-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.st-import-file-info-card__button {
    margin-left: auto;
    text-transform: none;
    letter-spacing: 0;
}

.st-import-file-info-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-end;
    margin-left: auto;
}

.st-import-file-info-card__actions :deep(.v-btn) {
    text-transform: none;
    letter-spacing: 0;
}

.st-import-file-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 8px;
}

.st-import-file-info-item {
    min-width: 0;
}

.st-import-file-info-item--wide {
    grid-column: 1 / -1;
}

.st-import-file-info-item span {
    display: block;
    color: rgba(0, 0, 0, 0.6);
    font-size: 0.72rem;
}

.st-import-file-info-item strong {
    display: block;
    font-size: 0.86rem;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.st-import-file-info-note {
    margin-top: 8px;
    color: rgba(0, 0, 0, 0.72);
    font-size: 0.82rem;
    font-weight: 400;
}

.st-import-file-info-note__lead {
    display: flex;
    align-items: center;
    gap: 6px;
}

.st-import-file-info-note p {
    margin: 2px 0 0;
    font-weight: 400;
}

.st-import-history-card {
    border: 1px solid rgba(37, 99, 235, 0.12);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.st-import-history-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.st-import-history-panel {
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.st-import-history-panel + .st-import-history-panel {
    margin-top: 6px;
}

.st-import-history-title {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-width: 0;
}

.st-import-history-delete-button {
    flex: 0 0 auto;
}

.st-import-history-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
}

.st-import-history-meta-item {
    min-width: 0;
}

.st-import-history-meta-section {
    grid-column: 1 / -1;
    margin-top: 4px;
    border-top: 1px solid rgba(25, 118, 210, 0.14);
    padding-top: 8px;
    color: rgba(30, 64, 175, 0.92);
    font-size: 0.78rem;
    font-weight: 700;
}

.st-import-history-meta-item span {
    display: block;
    color: rgba(0, 0, 0, 0.6);
    font-size: 0.72rem;
}

.st-import-history-meta-item strong {
    display: block;
    font-size: 0.86rem;
    overflow-wrap: anywhere;
}

.st-import-history-meta-item small {
    display: block;
    margin-top: 2px;
    color: rgba(0, 0, 0, 0.54);
    font-size: 0.74rem;
    overflow-wrap: anywhere;
}

.st-import-history-meta-item .st-import-history-inline-detail {
    display: inline;
    margin-top: 0;
}

.st-import-history-subject-table,
.st-import-history-teacher-table {
    grid-column: 1 / -1;
    width: 100%;
    border: 1px solid rgba(25, 118, 210, 0.12);
    border-radius: 8px;
    overflow: hidden;
}

.st-import-history-subject-table :deep(table),
.st-import-history-teacher-table :deep(table) {
    table-layout: fixed;
}

.st-import-history-subject-table :deep(.st-import-history-label-col),
.st-import-history-teacher-table :deep(.st-import-history-label-col) {
    width: 150px;
}

.st-import-history-subject-table :deep(th:first-child),
.st-import-history-subject-table :deep(td:first-child),
.st-import-history-teacher-table :deep(th:first-child),
.st-import-history-teacher-table :deep(td:first-child) {
    width: 150px;
    min-width: 150px;
    max-width: 150px;
}

.st-import-history-subject-table :deep(.st-import-history-count-col),
.st-import-history-teacher-table :deep(.st-import-history-count-col) {
    width: 48px;
}

.st-import-history-subject-table :deep(th),
.st-import-history-subject-table :deep(td) {
    height: 30px;
    padding: 0 4px !important;
    font-size: 0.78rem;
}

.st-import-history-subject-table :deep(th:not(:last-child)),
.st-import-history-subject-table :deep(td:not(:last-child)),
.st-import-history-teacher-table :deep(th:not(:last-child)),
.st-import-history-teacher-table :deep(td:not(:last-child)) {
    border-right: 1px solid rgba(25, 118, 210, 0.16);
}

.st-import-history-subject-percent-row td {
    border-top: none !important;
    height: 22px;
    padding: 0 2px !important;
    color: rgba(0, 0, 0, 0.5);
    font-size: 0.68rem;
}

.st-import-history-subject-sum-row td {
    border-top: 1px solid rgba(25, 118, 210, 0.14);
    font-weight: 700;
}

.st-import-history-subject-table :deep(.st-import-history-subject-total-cell) {
    font-weight: 700;
}

.st-import-history-subject-sum-percent-row td {
    font-weight: 700;
}

.st-import-history-teacher-table :deep(th),
.st-import-history-teacher-table :deep(td) {
    height: 30px;
    padding: 0 4px !important;
    font-size: 0.78rem;
}

.st-import-history-teacher-table :deep(.st-import-history-teacher-total-cell) {
    font-weight: 700;
}

.st-import-history-teacher-percent-row td {
    height: 22px;
    border-top: none !important;
    padding: 0 2px !important;
    color: rgba(0, 0, 0, 0.5);
    font-size: 0.68rem;
}

.st-import-history-teacher-sum-percent-row td {
    font-weight: 700;
}

.st-import-history-teacher-subjects-cell {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.st-import-history-teacher-sum-row td {
    border-top: 1px solid rgba(25, 118, 210, 0.14);
    font-weight: 700;
}

.st-tt-sub-row td {
    border-top: none !important;
    padding-top: 0 !important;
    color: rgba(30, 64, 175, 0.8);
}

.st-main-dataset-summary {
    border: 1px solid rgba(25, 118, 210, 0.22);
    border-radius: 8px;
    padding: 12px;
    background: #f8fbff;
}

.st-main-dataset-summary__header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.st-main-dataset-summary__title {
    min-width: 0;
    flex: 1;
}

.st-main-dataset-summary__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 8px;
}

.st-main-dataset-summary__item {
    min-width: 0;
}

.st-main-dataset-summary__item span {
    display: block;
    color: rgba(0, 0, 0, 0.6);
    font-size: 0.72rem;
}

.st-main-dataset-summary__item strong {
    display: block;
    font-size: 0.86rem;
    overflow-wrap: anywhere;
}

.st-course-summary {
    border: 1px solid rgba(25, 118, 210, 0.18);
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
}

.st-course-summary__panel {
    border: 0;
    background: transparent;
}

.st-course-summary__title {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-width: 0;
}

.st-course-summary :deep(.v-expansion-panel-title) {
    min-height: 44px;
    padding: 10px 12px;
}

.st-course-summary :deep(.v-expansion-panel-text__wrapper) {
    padding: 0 12px 12px;
}

.st-single-date-summary {
    border: 1px solid rgba(251, 140, 0, 0.28);
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
}

.st-single-date-summary__panel {
    border: 0;
    background: transparent;
}

.st-single-date-summary__title {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-width: 0;
}

.st-single-date-summary :deep(.v-expansion-panel-title) {
    min-height: 44px;
    padding: 10px 12px;
}

.st-single-date-summary :deep(.v-expansion-panel-text__wrapper) {
    padding: 0 12px 12px;
}

.st-single-date-saving-dots {
    padding: 0;
}

.st-single-date-saving-dots :deep(.loading-animation) {
    padding: 0;
}

.st-single-date-saving-dots :deep(.loading-dots) {
    gap: 5px;
}

.st-single-date-saving-dots :deep(.dot) {
    width: 7px;
    height: 7px;
}

.st-single-date-save-caption {
    caption-side: top;
    padding: 6px 8px 10px;
    color: rgba(0, 0, 0, 0.62);
    font-size: 0.78rem;
    text-align: left;
}

.st-single-date-course-row td {
    background: rgba(251, 140, 0, 0.08);
    border-top: 1px solid rgba(251, 140, 0, 0.18);
}

.st-single-date-select-col {
    width: 116px;
    min-width: 116px;
    white-space: nowrap;
}

.st-single-date-appointment-row--inactive td {
    color: rgba(0, 0, 0, 0.46);
    text-decoration: line-through;
}

@media (max-width: 640px) {
    .st-import-page-title {
        flex-wrap: wrap;
    }

    .st-import-back-button {
        margin-left: 0;
    }

    .st-import-file-info-card__title {
        flex-wrap: wrap;
    }

    .st-import-file-info-card__button {
        margin-left: 0;
    }

    .st-import-file-info-card__actions {
        margin-left: 0;
        width: 100%;
        justify-content: flex-start;
    }

    .st-import-history-title {
        flex-wrap: wrap;
    }

    .st-import-history-delete-button {
        flex-basis: 100%;
    }

    .st-main-dataset-summary__header {
        flex-wrap: wrap;
    }

    .st-import-history-subject-table :deep(table),
    .st-import-history-teacher-table :deep(table) {
        table-layout: auto;
    }

    .st-import-history-subject-table :deep(th:first-child),
    .st-import-history-subject-table :deep(td:first-child),
    .st-import-history-teacher-table :deep(th:first-child),
    .st-import-history-teacher-table :deep(td:first-child) {
        width: auto;
        min-width: 80px;
        max-width: none;
    }

    .st-single-date-select-col {
        width: 56px;
        min-width: 56px;
    }
}
</style>
