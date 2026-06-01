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

                <section
                    v-if="activeImportPage === 'faecher' && activeSubjectImport"
                    class="st-main-dataset-summary mb-4">
                    <div class="st-main-dataset-summary__header">
                        <v-icon icon="mdi-code-json" color="primary" size="20" />
                        <div class="st-main-dataset-summary__title">
                            <div class="text-caption text-medium-emphasis">Importdaten</div>
                            <div class="font-weight-bold">Aktive Fächer</div>
                        </div>
                        <v-chip size="small" color="primary" variant="tonal">
                            {{ subjectDataset.table || 'student_timetable_subject_rows' }}
                        </v-chip>
                    </div>

                    <div class="st-main-dataset-summary__grid">
                        <div
                            v-for="item in subjectImportSummaryItems"
                            :key="item.label"
                            class="st-main-dataset-summary__item">
                            <span>{{ item.label }}</span>
                            <strong>{{ item.value }}</strong>
                        </div>
                    </div>
                </section>

                <section
                    v-if="activeImportPage === 'faecher' && activeSubjectRows.length"
                    class="st-subject-summary mb-4">
                    <v-expansion-panels variant="accordion">
                        <v-expansion-panel elevation="0" class="st-subject-summary__panel">
                            <v-expansion-panel-title>
                                <div class="st-subject-summary__title">
                                    <v-icon icon="mdi-book-open-page-variant" color="primary" size="18" />
                                    <span class="font-weight-medium">Alle Fächer</span>
                                    <v-chip size="x-small" color="primary" variant="tonal">
                                        {{ activeSubjectRows.length }}
                                    </v-chip>
                                </div>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <div class="st-subject-semester-list">
                                    <section
                                        v-for="semesterGroup in activeSubjectRowsBySemester"
                                        :key="semesterGroup.key"
                                        class="st-subject-semester-block">
                                        <div class="st-subject-semester-block__header">
                                            <span>{{ semesterGroup.label }}</span>
                                            <v-chip size="x-small" color="primary" variant="tonal">
                                                {{ semesterGroup.count }}
                                            </v-chip>
                                        </div>

                                        <div
                                            v-for="branchGroup in semesterGroup.branchGroups"
                                            :key="branchGroup.key"
                                            class="st-subject-branch-block"
                                            :class="subjectBranchBlockClass(branchGroup.key)">
                                            <div class="st-subject-branch-block__header">
                                                <span>{{ branchGroup.label }}</span>
                                                <v-chip size="x-small" color="primary" variant="tonal">
                                                    {{ branchGroup.rows.length }}
                                                </v-chip>
                                            </div>

                                            <div class="st-subject-pill-list">
                                                <div
                                                    v-for="subjectRow in branchGroup.rows"
                                                    :key="subjectRowKey(subjectRow)"
                                                    class="st-subject-pill">
                                                    <span class="st-subject-pill__code">{{ subjectRow.json_code || subjectRow.json_subject || '-' }}</span>
                                                    <span class="st-subject-pill__separator">·</span>
                                                    <span class="st-subject-pill__name">{{ subjectRow.name || subjectRow.json_subject || '-' }}</span>
                                                    <span v-if="subjectHoursLabel(subjectRow) !== '-'" class="st-subject-pill__hours">
                                                        {{ subjectHoursLabel(subjectRow) }} WStd.
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </section>

                <v-alert
                    v-else-if="activeImportPage === 'faecher' && !loadingImportButtonInfo"
                    type="info"
                    variant="tonal"
                    class="mb-4">
                    Noch keine Fächer-Datei importiert.
                </v-alert>

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
                v-if="activeImportPage === 'faecher'"
                rounded="xl"
                class="st-import-file-info-card mt-3">
                <v-card-title class="st-import-file-info-card__title px-4 pt-4">
                    <v-icon icon="mdi-file-code-outline" size="20" color="primary" />
                    <span class="font-weight-bold">Benötigte Importdatei</span>
                    <div class="st-import-file-info-card__actions">
                        <v-btn
                            size="small"
                            variant="tonal"
                            color="warning"
                            prepend-icon="mdi-content-copy"
                            @click="copySubjectImportPrompt">
                            {{ subjectImportPromptCopied ? 'Kopiert' : 'Prompt kopieren' }}
                        </v-btn>
                        <v-btn
                            size="small"
                            variant="tonal"
                            color="primary"
                            prepend-icon="mdi-upload"
                            to="/admin/students-timetables/timetable/imports/faecher/import">
                            Import
                        </v-btn>
                    </div>
                </v-card-title>
                <v-card-text class="px-4 pb-4">
                    <div class="st-import-file-info-grid">
                        <div class="st-import-file-info-item">
                            <span>Dateityp</span>
                            <strong>JSON-Datei (.json)</strong>
                        </div>
                        <div class="st-import-file-info-item">
                            <span>Quelle</span>
                            <strong>KI-generiert</strong>
                        </div>
                        <div class="st-import-file-info-item">
                            <span>Struktur</span>
                            <strong>JSON mit Semestern, Fächern und Zweigen</strong>
                        </div>
                        <div class="st-import-file-info-item st-import-file-info-item--wide">
                            <span>Inhalt</span>
                            <strong>Fächer, Wochenstunden, Semester und Zweig-Varianten</strong>
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
                v-if="activeImportPage === 'faecher'"
                rounded="xl"
                class="st-import-history-card mt-3">
                <v-card-title class="st-import-history-card__title px-4 pt-4">
                    <v-icon icon="mdi-history" size="20" color="primary" />
                    <span class="font-weight-bold">Importverlauf</span>
                    <v-chip v-if="activeSubjectImport" size="x-small" variant="tonal" color="primary">
                        Letzter Import
                    </v-chip>
                </v-card-title>
                <v-card-text class="px-4 pb-4">
                    <v-alert v-if="!loadingImportButtonInfo && !activeSubjectImport" type="info" variant="tonal">
                        Noch keine Fächer-Datei importiert.
                    </v-alert>

                    <v-progress-linear v-if="loadingImportButtonInfo" indeterminate color="primary" class="mb-2" />

                    <div v-if="activeSubjectImport" class="st-subject-import-history-list">
                        <div class="st-subject-import-history-item">
                            <v-icon icon="mdi-code-json" color="primary" size="18" />
                            <div class="st-subject-import-history-item__content">
                                <div class="font-weight-medium">
                                    {{ activeSubjectImport.original_filename || activeSubjectImport.filename }}
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ formatDate(activeSubjectImport.uploaded_at) }}
                                </div>
                            </div>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ subjectImportCountLabel(activeSubjectImport) }}
                            </v-chip>
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
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useValidationRulesSetup } from '@/helpers/rules'
import FileUpload from '@/pages/components/FileUpload.vue'
import LoadingAnimation from '@/pages/components/LoadingAnimation.vue'
import Overview from '../overview/Overview.vue'

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
                subjects: null,
                recognitions: null,
                mainDataset: null,
                subjectDataset: null,
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
            subjectImportPromptCopied: false,
            uploadedFilename: '',
            refreshFilePond: 0,
            uploadError: '',
        }
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        configuredRoleNames() {
            return Array.isArray(this.config?.roles) ? this.config.roles : []
        },
        canManageTimetableImports() {
            return ['super_admin', 'admin', 'studentstimetables_admin'].some(roleName => this.configuredRoleNames.includes(roleName))
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
                    key: 'faecher',
                    label: 'Fächer',
                    icon: 'mdi-book-open-page-variant',
                    meta: this.importButtonDateMeta(this.importButtonInfo.subjects?.uploaded_at),
                    detail: this.subjectImportDetail,
                },
                {
                    key: 'anrechnungen',
                    label: 'Anrechnungen',
                    icon: 'mdi-check-decagram-outline',
                    meta: this.importButtonDateMeta(this.importButtonInfo.recognitions?.uploaded_at),
                    detail: this.recognitionImportDetail,
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
            if (this.activeImportPage === 'anrechnungen') return 'mdi-file-delimited-outline'
            return this.activeImportPage === 'faecher' ? 'mdi-code-json' : 'mdi-upload'
        },
        activeImportUploadTitle() {
            if (this.activeImportPage === 'anrechnungen') return 'CSV-Datei importieren'
            return this.activeImportPage === 'faecher' ? 'JSON-Datei importieren' : 'TXT-Datei importieren'
        },
        activeImportUploadMeta() {
            if (this.activeImportPage === 'anrechnungen') return 'Anrechnungen · Sokrates Bund'
            return this.activeImportPage === 'faecher' ? 'Fächer · JSON-Import' : 'Stundenplan · Untis-Export'
        },
        activeImportUploadDetail() {
            if (this.activeImportPage === 'anrechnungen') return 'CSV-Datei (.csv), mit Studierenden, Fächern und Noten'

            return this.activeImportPage === 'faecher'
                ? 'JSON-Datei (.json), mit Semestern, Fächern und Zweigen'
                : 'TXT-Datei (.txt), tabulatorgetrennt, mit TT-Einträgen'
        },
        activeImportUploadPath() {
            if (this.activeImportPage === 'anrechnungen') {
                return '/api/admin/students-timetables/recognitions-csv'
            }

            return this.activeImportPage === 'faecher'
                ? '/api/admin/students-timetables/subjects-overview-json'
                : '/api/admin/students-timetables/upload'
        },
        activeImportAllowedFileTypes() {
            if (this.activeImportPage === 'anrechnungen') return ['text/csv', 'application/csv', 'application/vnd.ms-excel']

            return this.activeImportPage === 'faecher' ? ['application/json'] : ['text/plain']
        },
        activeImportUploadVisible() {
            if (this.activeImportPage === 'anrechnungen') return true

            return this.activeImportPage === 'faecher' || Boolean(this.semester2StartRaw)
        },
        activeImportUploadAllowsMultiple() {
            return this.activeImportPage === 'anrechnungen'
        },
        activeImportUploadSuccessLabel() {
            if (this.activeImportPage === 'anrechnungen') return 'CSV-Datei'

            return this.activeImportPage === 'faecher' ? 'JSON-Datei' : 'TXT-Datei'
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
        activeSubjectImport() {
            return this.importButtonInfo.subjects
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
        subjectDataset() {
            return this.importButtonInfo.subjectDataset || {}
        },
        activeSubjectRows() {
            const subjectRows = this.activeSubjectImport?.analysis?.subject_rows

            return Array.isArray(subjectRows) ? subjectRows : []
        },
        activeSubjectRowsBySemester() {
            const branchOrder = ['common', 'wirtschaftskundlich', 'gymnasial']

            return Object.values(this.activeSubjectRows.reduce((semesters, subjectRow) => {
                const semester = subjectRow.semester || 'ohne'
                const semesterKey = `semester-${semester}`
                const branch = subjectRow.branch || 'common'

                if (!semesters[semesterKey]) {
                    semesters[semesterKey] = {
                        key: semesterKey,
                        semester,
                        label: semester === 'ohne' ? 'Ohne Semester' : `${semester}. Semester`,
                        branchGroupsByKey: {},
                    }
                }

                if (!semesters[semesterKey].branchGroupsByKey[branch]) {
                    semesters[semesterKey].branchGroupsByKey[branch] = {
                        key: branch,
                        label: this.subjectBranchGroupLabel(branch),
                        rows: [],
                    }
                }

                semesters[semesterKey].branchGroupsByKey[branch].rows.push(subjectRow)

                return semesters
            }, {}))
                .sort((firstSemester, secondSemester) => Number(firstSemester.semester) - Number(secondSemester.semester))
                .map(semesterGroup => {
                    const branchGroups = Object.values(semesterGroup.branchGroupsByKey)
                        .sort((firstBranch, secondBranch) =>
                            this.branchSortValue(firstBranch.key, branchOrder) - this.branchSortValue(secondBranch.key, branchOrder))
                        .map(branchGroup => ({
                            ...branchGroup,
                            rows: branchGroup.rows.sort((firstSubject, secondSubject) =>
                                this.compareText(firstSubject.json_code || firstSubject.name, secondSubject.json_code || secondSubject.name)),
                        }))

                    return {
                        ...semesterGroup,
                        count: branchGroups.reduce((total, branchGroup) => total + branchGroup.rows.length, 0),
                        branchGroups,
                    }
                })
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
        subjectImportSummaryItems() {
            const analysis = this.activeSubjectImport?.analysis || {}

            return [
                {
                    label: 'Semester',
                    value: this.subjectDataset.semesters_count ?? (Array.isArray(analysis.semesters) ? analysis.semesters.length : 0),
                },
                {
                    label: 'Fächer',
                    value: this.subjectDataset.subjects_count ?? Number(analysis.subjects_total || 0),
                },
                {
                    label: 'Zweige',
                    value: this.subjectDataset.branches_count ?? (Array.isArray(analysis.branches) ? analysis.branches.length : 0),
                },
                {
                    label: 'Zuletzt geändert',
                    value: this.formatDate(this.subjectDataset.updated_at) || '-',
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
        subjectImportDetail() {
            const importItem = this.importButtonInfo.subjects
            if (!importItem) return ''

            return [
                importItem.filename,
                this.subjectImportCountLabel(importItem),
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
        subjectImportPrompt() {
            return [
                'Erstelle eine valide JSON-Datei fuer den Faecher-Import in einer Schulverwaltungssoftware und stelle sie als herunterladbare Datei bereit.',
                'Der Dateiname soll faecher-vollstudium-abendgymnasium.json lauten.',
                '',
                'Analysiere diese Webseite inklusive eingebetteter Grafiken, Tabellen und Studienplan-Abbildungen:',
                'https://abendgymnasium.salzburg.at/vollstudium/',
                '',
                'Gib als Ergebnis eine herunterladbare .json-Datei aus. Der Dateiinhalt muss ausschliesslich valides JSON sein, ohne Markdown, ohne Kommentare und ohne Erklaertext.',
                'Falls deine Oberflaeche keine Datei direkt erzeugen kann, gib den kompletten JSON-Inhalt in einem einzigen json-Codeblock aus und nenne exakt den Dateinamen faecher-vollstudium-abendgymnasium.json.',
                '',
                'Erwartete Struktur:',
                '{',
                '  "course_abbreviations": {',
                '    "D": "Deutsch",',
                '    "E": "Englisch",',
                '    "M": "Mathematik"',
                '  },',
                '  "branches": {',
                '    "wirtschaftskundlich": "Wirtschaftskundlicher Zweig",',
                '    "gymnasial": "Gymnasialer Zweig"',
                '  },',
                '  "semesters": [',
                '    {',
                '      "semester": 1,',
                '      "common_courses": [',
                '        { "code": "D1", "subject": "D", "hours_per_week": 3 }',
                '      ],',
                '      "branches": {',
                '        "wirtschaftskundlich": [',
                '          { "code": "ÖKO2", "subject": "ÖKO", "hours_per_week": 2 }',
                '        ],',
                '        "gymnasial": [',
                '          { "code": "L6", "subject": "L", "hours_per_week": 3 }',
                '        ]',
                '      }',
                '    }',
                '  ]',
                '}',
                '',
                'Regeln:',
                '- Verwende pro Fach/Kurs ein Objekt mit code, subject und hours_per_week.',
                '- code ist das konkrete Modul/Kurskuerzel inklusive Semester-/Modulnummer, z. B. D1, M2, ÖKO2.',
                '- subject ist das Grundfachkuerzel ohne Modulnummer, z. B. D, M, ÖKO, L, F, S.',
                '- Gemeinsame Faecher kommen in common_courses.',
                '- Zweig-spezifische Faecher kommen unter branches.wirtschaftskundlich oder branches.gymnasial.',
                '- ÖKO 2/3 darf nicht als ein einzelnes Fach verloren gehen: bilde daraus getrennte Kurse ÖKO2 und ÖKO3, wenn beide Module gemeint sind. Teile die Wochenstunden sinnvoll auf, falls die Grafik nur eine Gesamtsumme zeigt.',
                '- Wahlalternativen werden als eigene Kursobjekte importiert, aber in der Anzeige zusammengefasst und nur einmal gezaehlt.',
                '- Sprachen: Unterscheide Deutsch, Englisch und weitere Sprachen sauber. Wahlsprachen wie Latein, Franzoesisch oder Spanisch als eigene Kuerzel L, F, S fuehren; nicht als einzelnes Kursobjekt L/F/S importieren. Beispiel: F1, L1 und S1 jeweils mit 4 Wochenstunden importieren; die Anzeige zeigt F1/L1/S1* mit 4 Wochenstunden.',
                '- Kunst/Musik: Bildnerische Erziehung und Musikerziehung sind Wahlalternativen, wenn sie in derselben Semester-/Zweig-Position stehen. Beispiel: BE1 und ME1 jeweils als eigenes Kursobjekt mit denselben Wochenstunden importieren; die Anzeige markiert sie mit * und zaehlt die Wochenstunden nur einmal.',
                '- Zweige: Wirtschaftskundliche Inhalte nur dem wirtschaftskundlichen Zweig zuordnen; gymnasiale/sprachliche Inhalte nur dem gymnasialen Zweig zuordnen.',
                '- Wenn in der Grafik ein Fach in mehreren Semestern vorkommt, lege fuer jedes Semester einen eigenen Kurs mit passender Nummer an.',
                '- Verwende Dezimalzahlen fuer halbe Wochenstunden, falls noetig.',
                '- Wenn ein Wert aus der Webseite nicht eindeutig lesbar ist, setze ihn nicht auf 0, sondern leite ihn aus der Grafik und dem Studienplan-Kontext ab.',
            ].join('\\n')
        },
    },
    watch: {
        'config.selected_schoolyear.id'() {
            this.loadImportButtonInfo()
        },
        '$route.params.subsection'(subsection) {
            this.subAction = this.normalizedSubAction(subsection)
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
            this.redirectLegacyOverviewRoute()
            this.loadImportButtonInfo()
        },
        '$route.params.action'(action) {
            this.importSubPage = this.normalizedImportSubPage(action)
        },
    },
    mounted() {
        this.redirectLegacyOverviewRoute()
        this.redirectLegacyRobotRoute()
        this.redirectUnauthorizedImportRoute()
        this.loadImportButtonInfo()
    },
    unmounted() {
        this.clearPolling()
        this.clearSingleDateActivationSaveTimer()
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

            const allowed = ['stundenplan', 'faecher', 'anrechnungen']

            return allowed.includes(detail) ? detail : ''
        },
        normalizedImportSubPage(action) {
            if (!this.canManageTimetableImports) return ''

            const allowed = ['import']

            return allowed.includes(action) ? action : ''
        },
        redirectUnauthorizedImportRoute() {
            if (this.$route.params.subsection !== 'imports' || this.canManageTimetableImports) return

            this.subAction = 'overview'
            this.importPage = ''
            this.importSubPage = ''
            this.$router.replace({ path: '/admin/students-timetables' })
        },
        redirectLegacyOverviewRoute() {
            if (
                this.$route.params.section !== 'timetable'
                || this.$route.params.subsection !== 'overview'
                || this.$route.params.detail
            ) {
                return false
            }

            this.subAction = 'overview'
            this.importPage = ''
            this.importSubPage = ''
            this.$router.replace({ path: '/admin/students-timetables' })

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
            this.$router.push({ path: `/admin/students-timetables/timetable/imports/${this.activeImportPage || 'stundenplan'}` })
        },
        onImportUploadStart() {
            this.uploadError = ''
            this.uploadedFilename = ''
        },
        onUploadFinished(file) {
            this.uploadError = ''
            if (this.activeImportPage === 'faecher') {
                this.uploadedFilename = file?.name || 'gespeichert'
                this.refreshFilePond++
                this.loadImportButtonInfo()

                return
            }

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
            if (this.activeImportPage === 'faecher') {
                this.uploadedFilename = ''
                this.uploadError = 'Die JSON-Datei konnte nicht gespeichert werden.'
                this.refreshFilePond++

                return
            }

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
        async copySubjectImportPrompt() {
            await navigator.clipboard.writeText(this.subjectImportPrompt)
            this.subjectImportPromptCopied = true

            window.setTimeout(() => {
                this.subjectImportPromptCopied = false
            }, 1800)
        },
        async loadImportButtonInfo() {
            if (this.subAction !== 'imports' || this.loadingImportButtonInfo) return

            this.loadingImportButtonInfo = true
            try {
                const shouldLoadFullTimetableImports = this.activeImportPage === 'stundenplan'
                const shouldLoadFullSubjectImports = this.activeImportPage === 'faecher'
                const shouldLoadFullRecognitionImports = this.activeImportPage === 'anrechnungen'

                const [timetableResponse, subjectsResponse, recognitionsResponse] = await Promise.all([
                    axios.get('/api/admin/students-timetables/imports', {
                        params: {
                            page: 1,
                            per_page: shouldLoadFullTimetableImports ? 100 : 1,
                            include_single_date_courses: shouldLoadFullTimetableImports ? 1 : 0,
                            summary: shouldLoadFullTimetableImports ? 0 : 1,
                        },
                    }),
                    axios.get('/api/admin/students-timetables/subjects-overview-json', {
                        params: {
                            summary: shouldLoadFullSubjectImports ? 0 : 1,
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
                    subjects: subjectsResponse.data?.data?.[0] || null,
                    recognitions: this.recognitionImports[0] || null,
                    mainDataset: timetableResponse.data?.main_dataset || null,
                    subjectDataset: subjectsResponse.data?.active_dataset || null,
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
                    subjects: null,
                    recognitions: null,
                    mainDataset: null,
                    subjectDataset: null,
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
        subjectImportCountLabel(importItem) {
            const subjectsTotal = Number(importItem?.analysis?.subjects_total || 0)

            return subjectsTotal ? `${subjectsTotal} Fächer` : ''
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
        subjectRowKey(subjectRow) {
            return [
                subjectRow?.semester || '',
                subjectRow?.branch || '',
                subjectRow?.json_code || '',
                subjectRow?.json_subject || '',
                subjectRow?.name || '',
            ].join('|')
        },
        subjectHoursLabel(subjectRow) {
            const hours = Number(subjectRow?.hours_per_week || 0)

            return hours > 0 ? `${hours}` : '-'
        },
        subjectBranchLabel(subjectRow) {
            if (subjectRow?.branch === 'wirtschaftskundlich') return 'Wirtschaftskundlicher Zweig'
            if (subjectRow?.branch === 'gymnasial') return 'Gymnasialer Zweig'

            return 'Gemeinsam'
        },
        subjectBranchGroupLabel(branch) {
            if (branch === 'wirtschaftskundlich') return 'Wirtschaftskundlicher Zweig'
            if (branch === 'gymnasial') return 'Gymnasialer Zweig'

            return 'alle'
        },
        subjectBranchBlockClass(branch) {
            if (branch === 'wirtschaftskundlich') return 'st-subject-branch-block--wirtschaftskundlich'
            if (branch === 'gymnasial') return 'st-subject-branch-block--gymnasial'

            return 'st-subject-branch-block--common'
        },
        branchSortValue(branch, branchOrder) {
            const index = branchOrder.indexOf(branch)

            return index === -1 ? branchOrder.length : index
        },
        compareText(firstValue, secondValue) {
            return String(firstValue || '').localeCompare(String(secondValue || ''), 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })
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

.st-subject-import-history-list {
    display: grid;
    gap: 8px;
}

.st-subject-import-history-item {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    border: 1px solid rgba(37, 99, 235, 0.12);
    border-radius: 8px;
    padding: 10px 12px;
    background: rgba(248, 251, 255, 0.9);
}

.st-subject-import-history-item__content {
    min-width: 0;
    flex: 1;
}

.st-subject-import-history-item__content .font-weight-medium,
.st-subject-import-history-item__content .text-caption {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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

.st-subject-summary {
    border: 1px solid rgba(25, 118, 210, 0.18);
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
}

.st-subject-summary__panel {
    border: 0;
    background: transparent;
}

.st-subject-summary__title {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-width: 0;
}

.st-subject-summary :deep(.v-expansion-panel-title) {
    min-height: 44px;
    padding: 10px 12px;
}

.st-subject-summary :deep(.v-expansion-panel-text__wrapper) {
    padding: 0 12px 12px;
}

.st-subject-semester-list {
    display: grid;
    gap: 10px;
}

.st-subject-semester-block {
    border: 1px solid rgba(37, 99, 235, 0.18);
    border-radius: 8px;
    padding: 8px;
    background: #f8fbff;
}

.st-subject-semester-block__header,
.st-subject-branch-block__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 0.86rem;
    font-weight: 700;
}

.st-subject-semester-block__header {
    margin-bottom: 8px;
}

.st-subject-branch-block {
    border: 1px solid rgba(37, 99, 235, 0.14);
    border-radius: 8px;
    padding: 8px;
    background: rgba(255, 255, 255, 0.72);
}

.st-subject-branch-block + .st-subject-branch-block {
    margin-top: 8px;
}

.st-subject-branch-block--wirtschaftskundlich {
    border-color: rgba(22, 163, 74, 0.28);
    background: rgba(187, 247, 208, 0.78);
}

.st-subject-branch-block--gymnasial {
    border-color: rgba(37, 99, 235, 0.26);
    background: rgba(191, 219, 254, 0.78);
}

.st-subject-pill-list {
    display: grid;
    gap: 5px;
    margin-top: 6px;
}

.st-subject-pill {
    display: flex;
    align-items: center;
    min-width: 0;
    border-radius: 999px;
    padding: 3px 12px;
    background: rgba(207, 213, 232, 0.82);
    color: #1d4ed8;
    font-size: 0.78rem;
    line-height: 1.3;
}

.st-subject-branch-block--wirtschaftskundlich .st-subject-pill {
    background: rgba(134, 220, 185, 0.72);
}

.st-subject-branch-block--gymnasial .st-subject-pill {
    background: rgba(147, 188, 240, 0.62);
}

.st-subject-pill__code {
    flex: 0 0 auto;
    font-weight: 700;
}

.st-subject-pill__separator {
    flex: 0 0 auto;
    margin: 0 4px;
}

.st-subject-pill__name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.st-subject-pill__hours {
    flex: 0 0 auto;
    margin-left: auto;
    padding-left: 8px;
    color: rgba(30, 64, 175, 0.72);
    font-size: 0.72rem;
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
