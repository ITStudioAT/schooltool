<template>
    <v-col cols="12" class="tests-v3-page">
        <div class="tests-v3-page__title-row">
            <h2 class="tests-v3-page__title">Tests für Stundenplan Version 3</h2>
            <strong class="tests-v3-page__schoolyear text-primary">{{ personalSchoolyearLabel }}</strong>
        </div>

        <v-sheet rounded="lg" class="tests-v3-subnav">
            <v-btn
                v-for="item in testsV3NavigationItems"
                :key="item.key"
                size="small"
                :color="testsV3Action === item.key ? 'primary' : 'secondary'"
                :variant="testsV3Action === item.key ? 'flat' : 'tonal'"
                :prepend-icon="item.icon"
                class="tests-v3-subnav__button"
                @click="handleTestsV3Navigation(item.key)">
                {{ item.label }}
            </v-btn>
        </v-sheet>

        <v-alert
            v-if="testReadinessLoading"
            type="info"
            variant="tonal"
            prominent
            border="start"
            class="mb-4 tests-v3-readiness">
            Die Voraussetzungen für Test V3 werden geprüft.
        </v-alert>

        <v-alert
            v-else-if="testReadinessError"
            type="error"
            variant="tonal"
            prominent
            border="start"
            title="Voraussetzungen konnten nicht geprüft werden"
            class="mb-4 tests-v3-readiness">
            <div class="mb-3">
                Test V3 bleibt gesperrt, weil die Vollständigkeit der Importdaten nicht bestätigt werden konnte.
            </div>
            <v-btn size="small" color="error" variant="outlined" @click="loadTestReadiness(true)">
                Erneut prüfen
            </v-btn>
        </v-alert>

        <v-alert
            v-else-if="testReadiness && !testReadinessReady"
            type="error"
            variant="tonal"
            prominent
            border="start"
            :title="testReadiness.title"
            class="mb-4 tests-v3-readiness">
            <p class="mb-3">{{ testReadiness.message }}</p>
            <v-list bg-color="transparent" density="compact" class="pa-0 tests-v3-readiness__issues">
                <v-list-item
                    v-for="issue in testReadinessIssues"
                    :key="issue.code"
                    prepend-icon="mdi-alert-octagon-outline"
                    class="px-0">
                    <v-list-item-title class="font-weight-bold">{{ issue.title }}</v-list-item-title>
                    <v-list-item-subtitle class="text-wrap">{{ issue.message }}</v-list-item-subtitle>
                    <div class="text-body-2 mt-1"><strong>Nächster Schritt:</strong> {{ issue.next_step }}</div>
                </v-list-item>
            </v-list>
            <v-btn class="mt-3" size="small" color="error" variant="outlined" @click="loadTestReadiness(true)">
                Voraussetzungen neu prüfen
            </v-btn>
        </v-alert>

        <v-card
            v-if="testsV3Action === 'students'"
            rounded="lg"
            border
            class="tests-v3-students">
            <v-card-title class="tests-v3-students__header d-flex flex-wrap align-center ga-3">
                <div>
                    <div class="tests-v3-students__title">Studierende</div>
                    <div class="text-caption text-medium-emphasis">
                        {{ selectedStudentCount }} von {{ sortedStudents.length }} ausgewählt
                    </div>
                </div>
                <v-spacer />
                <div class="tests-v3-students__actions d-flex flex-wrap ga-2">
                    <v-btn
                        size="small"
                        color="primary"
                        variant="tonal"
                        prepend-icon="mdi-checkbox-multiple-marked-outline"
                        :disabled="studentsLoading || !sortedStudents.length || allStudentsSelected"
                        @click="selectAllStudents">
                        Alle auswählen
                    </v-btn>
                    <v-btn
                        size="small"
                        color="error"
                        variant="tonal"
                        prepend-icon="mdi-checkbox-multiple-blank-outline"
                        :disabled="studentsLoading || !selectedStudentCount"
                        @click="clearStudentSelection">
                        Keine auswählen
                    </v-btn>
                </div>
            </v-card-title>

            <v-card-text
                v-if="sortedStudents.length"
                class="tests-v3-students__classes">
                <div class="tests-v3-students__selection-group">
                    <span class="tests-v3-students__classes-label">Semester</span>
                    <div class="tests-v3-students__semester-buttons">
                        <v-btn
                            v-for="studentSemester in studentSemesters"
                            :key="studentSemester.key"
                            :color="isStudentSemesterSelected(studentSemester) ? 'primary' : 'secondary'"
                            :variant="isStudentSemesterSelected(studentSemester) ? 'flat' : 'tonal'"
                            :prepend-icon="isStudentSemesterSelected(studentSemester) ? 'mdi-check' : 'mdi-calendar-range'"
                            :disabled="!studentSemester.studentKeys.length"
                            :aria-pressed="isStudentSemesterSelected(studentSemester)"
                            :aria-label="`${studentSemester.label} ${isStudentSemesterSelected(studentSemester) ? 'abwählen' : 'auswählen'}`"
                            size="small"
                            class="tests-v3-students__semester-button"
                            @click="toggleStudentSemesterSelection(studentSemester)">
                            {{ studentSemester.label }}
                        </v-btn>
                    </div>
                </div>
                <div class="tests-v3-students__selection-group">
                    <span class="tests-v3-students__classes-label">Klassen</span>
                    <div class="tests-v3-students__class-chips">
                        <v-chip
                            v-for="studentClass in studentClasses"
                            :key="studentClass.key"
                            :color="isStudentClassSelected(studentClass) ? 'primary' : 'secondary'"
                            :variant="isStudentClassSelected(studentClass) ? 'flat' : 'tonal'"
                            :prepend-icon="isStudentClassSelected(studentClass) ? 'mdi-check' : 'mdi-account-group-outline'"
                            :disabled="!studentClass.studentKeys.length"
                            :aria-pressed="isStudentClassSelected(studentClass)"
                            :aria-label="`Klasse ${studentClass.label} ${isStudentClassSelected(studentClass) ? 'abwählen' : 'auswählen'}`"
                            size="small"
                            label
                            class="tests-v3-students__class-chip"
                            @click="toggleStudentClassSelection(studentClass)">
                            {{ studentClass.label }}
                        </v-chip>
                    </div>
                </div>
            </v-card-text>

            <v-progress-linear v-if="studentsLoading" color="primary" indeterminate />

            <v-card-text v-if="studentsError" class="pt-4">
                <v-alert type="error" variant="tonal">
                    <div class="d-flex flex-wrap align-center justify-space-between ga-3">
                        <span>Die Studierenden konnten nicht geladen werden.</span>
                        <v-btn size="small" color="error" variant="outlined" @click="loadStudents">
                            Erneut versuchen
                        </v-btn>
                    </div>
                </v-alert>
            </v-card-text>

            <v-card-text v-else-if="!studentsLoading && !sortedStudents.length" class="pt-4">
                <v-alert type="info" variant="tonal">
                    Für {{ personalSchoolyearLabel }} sind keine Studierenden vorhanden.
                </v-alert>
            </v-card-text>

            <v-table
                v-else
                fixed-header
                hover
                density="comfortable"
                height="min(62vh, 720px)"
                class="tests-v3-students__table">
                <thead>
                    <tr>
                        <th class="tests-v3-students__selection-column">
                            <v-checkbox-btn
                                :model-value="allStudentsSelected"
                                :indeterminate="someStudentsSelected && !allStudentsSelected"
                                :disabled="studentsLoading || !sortedStudents.length"
                                color="primary"
                                density="compact"
                                aria-label="Alle Studierenden auswählen oder Auswahl aufheben"
                                @update:model-value="toggleAllStudents" />
                        </th>
                        <th class="tests-v3-students__class-column">Klasse</th>
                        <th class="tests-v3-students__name-column">Name</th>
                        <th class="tests-v3-students__study-selection-column">Studienauswahl</th>
                        <th class="tests-v3-students__semester-column">Semester</th>
                        <th class="tests-v3-students__course-results-column">Befreit/Bestanden</th>
                        <th class="tests-v3-students__course-results-column">Negativ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="student in sortedStudents"
                        :key="studentSelectionKey(student)"
                        :class="{
                            'tests-v3-students__row--selected': isStudentSelected(student),
                            'tests-v3-students__row--invalid-data': studentDataQualityIssues(student).length,
                        }">
                        <td class="tests-v3-students__selection-column">
                            <v-checkbox-btn
                                :model-value="isStudentSelected(student)"
                                color="primary"
                                density="compact"
                                :aria-label="`${studentDisplayName(student)} auswählen`"
                                @update:model-value="toggleStudentSelection(student, $event)" />
                        </td>
                        <td class="font-weight-bold">{{ studentClassLabel(student) }}</td>
                        <td>
                            <div class="tests-v3-students__identity">
                                <span class="tests-v3-students__name">
                                    <span>{{ studentDisplayName(student) }}</span>
                                    <span
                                        v-if="studentReligionLabel(student)"
                                        class="tests-v3-students__religion">
                                        {{ studentReligionLabel(student) }}
                                    </span>
                                    <v-icon
                                        v-if="studentSexPresentation(student)"
                                        :icon="studentSexPresentation(student).icon"
                                        :color="studentSexPresentation(student).color"
                                        :title="studentSexPresentation(student).label"
                                        :aria-label="studentSexPresentation(student).label"
                                        size="21" />
                                </span>
                                <button
                                    v-if="studentEmail(student)"
                                    type="button"
                                    class="tests-v3-students__email"
                                    :class="{
                                        'tests-v3-students__email--copied': studentEmailCopyStatus(student) === 'copied',
                                        'tests-v3-students__email--failed': studentEmailCopyStatus(student) === 'failed',
                                    }"
                                    :title="studentEmailCopyTitle(student)"
                                    :aria-label="studentEmailCopyTitle(student)"
                                    @click.stop="copyStudentEmail(student)">
                                    <v-icon icon="mdi-email-outline" size="14" />
                                    <span>{{ studentEmail(student) }}</span>
                                    <v-icon
                                        :icon="studentEmailCopyStatus(student) === 'copied'
                                            ? 'mdi-check'
                                            : studentEmailCopyStatus(student) === 'failed'
                                                ? 'mdi-alert-circle-outline'
                                                : 'mdi-content-copy'"
                                        size="13" />
                                </button>
                            </div>
                        </td>
                        <td>
                            <div
                                v-if="studentStudySelectionLabels(student).length"
                                class="tests-v3-students__study-selection">
                                <span
                                    v-for="selectionLabel in studentStudySelectionLabels(student)"
                                    :key="selectionLabel"
                                    class="tests-v3-students__study-selection-item">
                                    {{ selectionLabel }}
                                </span>
                            </div>
                            <span v-else class="text-medium-emphasis">–</span>
                        </td>
                        <td class="tests-v3-students__semester">
                            <template v-if="studentSemesterLabel(student) || studentSchoolLevelLabel(student)">
                                <span class="tests-v3-students__semester-main">{{ studentSemesterLabel(student) }}</span>
                                <span
                                    v-if="studentSchoolLevelLabel(student)"
                                    class="tests-v3-students__semester-school-level">
                                    ({{ studentSchoolLevelLabel(student) }})
                                </span>
                            </template>
                            <span v-else class="text-medium-emphasis">–</span>
                            <div
                                v-if="studentDataQualityIssues(student).length"
                                class="tests-v3-students__data-quality-error">
                                <v-chip
                                    color="error"
                                    prepend-icon="mdi-database-alert"
                                    size="x-small"
                                    variant="flat"
                                    label>
                                    Falsche Daten
                                </v-chip>
                                <span
                                    v-for="issue in studentDataQualityIssues(student)"
                                    :key="issue"
                                    class="tests-v3-students__data-quality-message">
                                    {{ issue }}
                                </span>
                            </div>
                        </td>
                        <td class="tests-v3-students__course-results">
                            <template v-if="studentCourseResultItems(student, 'completed').length">
                                <span
                                    v-for="courseResult in studentCourseResultItems(student, 'completed')"
                                    :key="courseResult.key"
                                    class="tests-v3-students__course-result">
                                    {{ courseResult.code }} (<strong
                                        class="tests-v3-students__course-result-grade tests-v3-students__course-result-grade--completed">{{ courseResult.grade }}</strong>)
                                </span>
                            </template>
                            <span v-else class="text-medium-emphasis">–</span>
                        </td>
                        <td class="tests-v3-students__course-results">
                            <template v-if="studentCourseResultItems(student, 'negative').length">
                                <span
                                    v-for="courseResult in studentCourseResultItems(student, 'negative')"
                                    :key="courseResult.key"
                                    class="tests-v3-students__course-result">
                                    {{ courseResult.code }} (<strong
                                        class="tests-v3-students__course-result-grade tests-v3-students__course-result-grade--negative">{{ courseResult.grade }}</strong>)
                                </span>
                            </template>
                            <span v-else class="text-medium-emphasis">–</span>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>

        <template v-else-if="testsV3Action === 'tests'">
            <v-card
                rounded="lg"
                border
                class="tests-v3-study-plans">
                <v-card-title class="tests-v3-study-plans__header d-flex flex-wrap align-center ga-3">
                    <v-icon icon="mdi-book-open-page-variant-outline" color="primary" size="28" />
                    <div>
                        <div class="tests-v3-study-plans__title">Module nach Semester</div>
                        <div class="text-caption text-medium-emphasis">
                            Fachpläne für {{ personalSchoolyearLabel }}
                        </div>
                    </div>
                </v-card-title>

                <v-progress-linear v-if="studyPlansLoading" color="primary" indeterminate />

                <v-card-text v-if="studyPlansError" class="pt-4">
                    <v-alert type="error" variant="tonal">
                        <div class="d-flex flex-wrap align-center justify-space-between ga-3">
                            <span>{{ studyPlansError }}</span>
                            <v-btn size="small" color="error" variant="outlined" @click="loadStudyPlans">
                                Erneut versuchen
                            </v-btn>
                        </div>
                    </v-alert>
                </v-card-text>

                <v-card-text v-else class="tests-v3-study-plans__content">
                    <v-expansion-panels
                        v-model="expandedStudyPlans"
                        multiple
                        flat
                        tile
                        variant="accordion"
                        class="tests-v3-study-plans__panels">
                        <v-expansion-panel
                            v-for="studyPlan in studyPlanSections"
                            :key="studyPlan.key"
                            :value="studyPlan.key"
                            class="tests-v3-study-plan">
                            <v-expansion-panel-title class="tests-v3-study-plan__title">
                                <div class="tests-v3-study-plan__heading">
                                    <v-icon :icon="studyPlan.icon" :color="studyPlan.color" size="24" />
                                    <h3>{{ studyPlan.label }}</h3>
                                </div>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <v-alert v-if="!studyPlan.semesters.length" type="info" variant="tonal">
                                    Für {{ studyPlan.label }} sind keine aktiven Module hinterlegt.
                                </v-alert>

                                <div v-else class="tests-v3-study-plan__semesters">
                                    <div
                                        v-for="semester in studyPlan.semesters"
                                        :key="`${studyPlan.key}:${semester.semester}`"
                                        class="tests-v3-study-plan__semester">
                                        <div class="tests-v3-study-plan__semester-label">
                                            {{ semester.semester }}. Semester
                                        </div>
                                        <div class="tests-v3-study-plan__modules">
                                            <v-chip
                                                v-for="module in semester.modules"
                                                :key="module.key"
                                                :color="studyPlan.color"
                                                :title="module.name"
                                                size="small"
                                                variant="tonal"
                                                label>
                                                {{ module.code }}<template v-if="module.branchLabel"> · {{ module.branchLabel }}</template>
                                            </v-chip>
                                        </div>
                                    </div>
                                </div>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </v-card-text>
            </v-card>

            <v-card
                rounded="lg"
                border
                class="tests-v3-selection">
            <v-card-title class="tests-v3-selection__header d-flex flex-wrap align-center ga-3">
                <div>
                    <div class="tests-v3-selection__title">Ausgewählte Studierende</div>
                    <div class="text-caption text-medium-emphasis">
                        {{ selectedStudents.length }} Studierende für die Tests übernommen
                    </div>
                </div>
                <v-spacer />
                <v-btn
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-play-circle-outline"
                    :loading="studentV3TestsRunning"
                    :disabled="studentV3TestsRunning || testReadinessLoading || !testReadinessReady || !selectedStudents.length"
                    @click="runTests"
                    class="tests-v3-selection__run-button">
                    Run Tests
                </v-btn>
            </v-card-title>
            <div
                v-if="showStudentV3TestProgress"
                class="tests-v3-selection__progress">
                <v-progress-linear
                    :model-value="studentV3TestProgressPercentage"
                    color="primary"
                    height="8"
                    rounded />
                <div class="tests-v3-selection__progress-summary text-caption">
                    <span>
                        {{ completedStudentV3TestCount }} von {{ selectedStudents.length }} geprüft
                        <template v-if="runningStudentV3TestCount">
                            · {{ runningStudentV3TestCount }} in Bearbeitung
                        </template>
                        <template v-if="failedStudentV3TestCount">
                            · {{ failedStudentV3TestCount }} fehlgeschlagen
                        </template>
                        <template v-if="invalidStudentV3TestCount">
                            · {{ invalidStudentV3TestCount }} falsche Datensätze
                        </template>
                    </span>
                    <strong>{{ studentV3TestProgressPercentage }} %</strong>
                </div>
            </div>
            <v-card-text v-if="!selectedStudents.length">
                <v-alert v-if="!selectedStudents.length" type="info" variant="tonal">
                    Unter „Studierende“ wurden noch keine Studierenden ausgewählt.
                </v-alert>
            </v-card-text>
            <v-table
                v-else-if="!studentV3TestsRunning"
                fixed-header
                hover
                density="comfortable"
                class="tests-v3-students__table tests-v3-selection__table">
                <thead>
                    <tr>
                        <th class="tests-v3-students__selection-column" aria-label="Teststatus" />
                        <th class="tests-v3-students__class-column">Klasse</th>
                        <th class="tests-v3-students__name-column">Name</th>
                        <th class="tests-v3-students__study-selection-column">Studienauswahl</th>
                        <th class="tests-v3-students__semester-column">Semester</th>
                    </tr>
                </thead>
                <tbody>
                    <template
                        v-for="student in selectedStudents"
                        :key="studentSelectionKey(student)"
                        v-memo="[studentV3TestResult(student), studentEmailCopyStatus(student)]">
                        <tr :class="[
                            'tests-v3-students__row--selected',
                            { 'tests-v3-students__row--invalid-data': studentDataQualityIssues(student).length },
                        ]">
                        <td class="tests-v3-students__selection-column">
                            <v-icon
                                v-bind="studentV3TestStatusPresentation(student)"
                                size="22"
                                class="tests-v3-selection__student-test-status" />
                        </td>
                        <td class="font-weight-bold">{{ studentClassLabel(student) }}</td>
                        <td>
                            <div class="tests-v3-students__identity">
                                <span class="tests-v3-students__name">
                                    <span>{{ studentDisplayName(student) }}</span>
                                    <span
                                        v-if="studentReligionLabel(student)"
                                        class="tests-v3-students__religion">
                                        {{ studentReligionLabel(student) }}
                                    </span>
                                    <v-icon
                                        v-if="studentSexPresentation(student)"
                                        :icon="studentSexPresentation(student).icon"
                                        :color="studentSexPresentation(student).color"
                                        :title="studentSexPresentation(student).label"
                                        :aria-label="studentSexPresentation(student).label"
                                        size="21" />
                                </span>
                                <button
                                    v-if="studentEmail(student)"
                                    type="button"
                                    class="tests-v3-students__email"
                                    :class="{
                                        'tests-v3-students__email--copied': studentEmailCopyStatus(student) === 'copied',
                                        'tests-v3-students__email--failed': studentEmailCopyStatus(student) === 'failed',
                                    }"
                                    :title="studentEmailCopyTitle(student)"
                                    :aria-label="studentEmailCopyTitle(student)"
                                    @click.stop="copyStudentEmail(student)">
                                    <v-icon icon="mdi-email-outline" size="14" />
                                    <span>{{ studentEmail(student) }}</span>
                                    <v-icon
                                        :icon="studentEmailCopyStatus(student) === 'copied'
                                            ? 'mdi-check'
                                            : studentEmailCopyStatus(student) === 'failed'
                                                ? 'mdi-alert-circle-outline'
                                                : 'mdi-content-copy'"
                                        size="13" />
                                </button>
                            </div>
                        </td>
                        <td>
                            <div
                                v-if="studentStudySelectionLabels(student).length"
                                class="tests-v3-students__study-selection">
                                <span
                                    v-for="selectionLabel in studentStudySelectionLabels(student)"
                                    :key="selectionLabel"
                                    class="tests-v3-students__study-selection-item">
                                    {{ selectionLabel }}
                                </span>
                            </div>
                            <span v-else class="text-medium-emphasis">–</span>
                        </td>
                        <td class="tests-v3-students__semester">
                            <template v-if="studentSemesterLabel(student) || studentSchoolLevelLabel(student)">
                                <span class="tests-v3-students__semester-main">{{ studentSemesterLabel(student) }}</span>
                                <span
                                    v-if="studentSchoolLevelLabel(student)"
                                    class="tests-v3-students__semester-school-level">
                                    ({{ studentSchoolLevelLabel(student) }})
                                </span>
                            </template>
                            <span v-else class="text-medium-emphasis">–</span>
                            <div
                                v-if="studentDataQualityIssues(student).length"
                                class="tests-v3-students__data-quality-error">
                                <v-chip
                                    color="error"
                                    prepend-icon="mdi-database-alert"
                                    size="x-small"
                                    variant="flat"
                                    label>
                                    Falsche Daten
                                </v-chip>
                                <span
                                    v-for="issue in studentDataQualityIssues(student)"
                                    :key="issue"
                                    class="tests-v3-students__data-quality-message">
                                    {{ issue }}
                                </span>
                            </div>
                        </td>
                        </tr>
                        <tr class="tests-v3-selection__module-test-row">
                            <td colspan="5" class="tests-v3-selection__module-test">
                                <div class="tests-v3-selection__module-test-title">V3-Modultest</div>
                                <div
                                    v-if="studentV3TestResult(student)?.status === 'invalid_data'"
                                    class="tests-v3-selection__invalid-data-status text-error">
                                    <v-icon icon="mdi-database-alert" size="20" />
                                    <div>
                                        <strong>Falsche Daten – Test übersprungen</strong>
                                        <div>{{ studentV3TestResult(student).message }}</div>
                                    </div>
                                </div>
                                <div
                                    v-else-if="studentV3TestResult(student)?.status === 'running'"
                                    class="tests-v3-selection__test-status text-primary">
                                    <v-progress-circular color="primary" indeterminate size="18" width="2" />
                                    V3-Berechnung läuft …
                                </div>
                                <div
                                    v-else-if="studentV3TestResult(student)?.status === 'pending'"
                                    class="tests-v3-selection__test-status text-medium-emphasis">
                                    <v-icon icon="mdi-clock-outline" size="18" />
                                    Wartet auf Prüfung …
                                </div>
                                <div
                                    v-else-if="studentV3TestResult(student)?.status === 'error'"
                                    class="tests-v3-selection__test-status text-error">
                                    <v-icon icon="mdi-alert-circle-outline" size="18" />
                                    {{ studentV3TestResult(student).message }}
                                </div>
                                <div
                                    v-else-if="studentV3TestResult(student)?.status === 'complete'"
                                    class="tests-v3-selection__module-groups">
                                    <div
                                        v-for="group in studentV3TestResult(student).groups"
                                        :key="group.key"
                                        :class="[
                                            'tests-v3-selection__module-group',
                                            `tests-v3-selection__module-group--${group.key}`,
                                        ]">
                                        <div class="tests-v3-selection__module-group-header">
                                            <span>{{ group.label }}</span>
                                            <v-chip
                                                :color="group.color"
                                                size="x-small"
                                                variant="tonal"
                                                label>
                                                {{ group.count }}
                                            </v-chip>
                                        </div>
                                        <div
                                            v-if="['finished', 'negative', 'previous', 'current', 'additional'].includes(group.key)"
                                            class="tests-v3-selection__module-comparison">
                                            <div class="tests-v3-selection__module-comparison-section">
                                                <span class="tests-v3-selection__module-comparison-label">Soll-Module</span>
                                                <div class="tests-v3-selection__module-codes">
                                                    <span
                                                        v-for="module in group.comparison.expectedModules"
                                                        :key="module.key"
                                                        :title="module.title"
                                                        class="tests-v3-selection__module-code">
                                                        {{ module.code }}<template v-if="module.grade"> (<strong
                                                            :class="group.key === 'finished'
                                                                ? 'tests-v3-students__course-result-grade--completed'
                                                                : 'tests-v3-students__course-result-grade--negative'">{{ module.grade }}</strong>)</template>
                                                    </span>
                                                    <span
                                                        v-if="!group.comparison.expectedModules.length"
                                                        class="text-medium-emphasis">–</span>
                                                </div>
                                            </div>
                                            <template v-if="group.comparison.mismatches.length">
                                                <v-divider class="tests-v3-selection__module-comparison-divider" />
                                                <div class="tests-v3-selection__module-comparison-section">
                                                    <span class="tests-v3-selection__module-comparison-label">Abweichende Module</span>
                                                    <div class="tests-v3-selection__module-codes">
                                                        <span
                                                            v-for="module in group.comparison.mismatches"
                                                            :key="module.key"
                                                            :title="module.title"
                                                            class="tests-v3-selection__module-code">
                                                            {{ module.code }}<template v-if="module.grade"> (<strong
                                                                :class="group.key === 'finished'
                                                                    ? 'tests-v3-students__course-result-grade--completed'
                                                                    : 'tests-v3-students__course-result-grade--negative'">{{ module.grade }}</strong>)</template>
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>
                                            <div class="tests-v3-selection__module-comparison-result">
                                                <v-chip
                                                    v-if="group.comparison.matches !== null"
                                                    :color="group.comparison.matches ? 'success' : 'error'"
                                                    :prepend-icon="group.comparison.matches ? 'mdi-check-circle' : 'mdi-alert-circle'"
                                                    :title="group.comparison.matches
                                                        ? 'Soll-Module und V3-Ergebnis stimmen überein.'
                                                        : 'Soll-Module und V3-Ergebnis stimmen nicht überein.'"
                                                    size="x-small"
                                                    variant="flat"
                                                    label
                                                    class="tests-v3-selection__module-match">
                                                    {{ group.comparison.matches ? 'OK' : 'FAIL' }}
                                                </v-chip>
                                            </div>
                                        </div>
                                        <div v-else class="tests-v3-selection__module-codes">
                                            <span
                                                v-for="module in group.modules"
                                                :key="`${group.key}:${module.code}`"
                                                :title="module.name"
                                                class="tests-v3-selection__module-code">
                                                {{ module.code }}
                                            </span>
                                            <span v-if="!group.modules.length" class="text-medium-emphasis">–</span>
                                        </div>
                                    </div>
                                </div>
                                <span v-else class="text-medium-emphasis">Noch nicht geprüft</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </v-table>
            </v-card>
        </template>

        <v-dialog
            v-model="studentV3TestSummaryDialog"
            persistent
            scrollable
            max-width="760"
            data-testid="student-v3-test-summary-dialog">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon
                        :color="failedStudentV3TestCount || invalidStudentV3TestCount ? 'error' : 'success'"
                        :icon="failedStudentV3TestCount || invalidStudentV3TestCount ? 'mdi-alert-circle' : 'mdi-check-circle'" />
                    Testzusammenfassung
                </v-card-title>
                <v-divider />
                <v-card-text class="tests-v3-test-summary__content pa-5">
                    <v-alert
                        :type="failedStudentV3TestCount || invalidStudentV3TestCount ? 'error' : 'success'"
                        variant="tonal"
                        class="mb-4">
                        <template v-if="failedStudentV3TestCount">
                            {{ failedStudentV3TestCount }} von {{ testedStudentV3TestCount }} Tests sind fehlgeschlagen.
                            <span v-if="invalidStudentV3TestCount">
                                {{ invalidStudentV3TestCount }} Datensätze wurden wegen falscher Daten nicht getestet.
                            </span>
                        </template>
                        <template v-else-if="invalidStudentV3TestCount">
                            {{ invalidStudentV3TestCount }} Datensätze wurden wegen falscher Daten nicht getestet.
                        </template>
                        <template v-else>
                            Alle {{ completedStudentV3TestCount }} Tests wurden erfolgreich abgeschlossen.
                        </template>
                    </v-alert>

                    <div class="d-flex flex-wrap ga-2 mb-4">
                        <v-chip color="primary" variant="tonal" prepend-icon="mdi-account-group">
                            Geprüft: {{ testedStudentV3TestCount }}
                        </v-chip>
                        <v-chip color="success" variant="tonal" prepend-icon="mdi-check-circle">
                            Bestanden: {{ passedStudentV3TestCount }}
                        </v-chip>
                        <v-chip color="error" variant="tonal" prepend-icon="mdi-alert-circle">
                            Fehlgeschlagen: {{ failedStudentV3TestCount }}
                        </v-chip>
                        <v-chip color="error" variant="tonal" prepend-icon="mdi-database-alert">
                            Falsche Daten: {{ invalidStudentV3TestCount }}
                        </v-chip>
                    </div>

                    <template v-if="failedStudentV3TestSummaries.length">
                        <div class="text-subtitle-2 mb-2">Fehlgeschlagene Tests</div>
                        <v-list border rounded="lg" density="compact" lines="two">
                            <v-list-item
                                v-for="summary in failedStudentV3TestSummaries"
                                :key="summary.key"
                                prepend-icon="mdi-alert-circle-outline">
                                <v-list-item-title>
                                    {{ summary.classLabel }} · {{ summary.studentName }}
                                </v-list-item-title>
                                <v-list-item-subtitle>{{ summary.message }}</v-list-item-subtitle>
                                <template #append>
                                    <v-chip color="error" size="x-small" variant="flat" label>FAIL</v-chip>
                                </template>
                            </v-list-item>
                        </v-list>
                    </template>

                    <template v-if="invalidStudentV3TestSummaries.length">
                        <div class="text-subtitle-2 mt-4 mb-2">Nicht getestete Datensätze</div>
                        <v-list border rounded="lg" density="compact" lines="two">
                            <v-list-item
                                v-for="summary in invalidStudentV3TestSummaries"
                                :key="summary.key"
                                prepend-icon="mdi-database-alert">
                                <v-list-item-title>
                                    {{ summary.classLabel }} · {{ summary.studentName }}
                                </v-list-item-title>
                                <v-list-item-subtitle>{{ summary.message }}</v-list-item-subtitle>
                                <template #append>
                                    <v-chip color="error" size="x-small" variant="flat" label>
                                        FALSCHE DATEN
                                    </v-chip>
                                </template>
                            </v-list-item>
                        </v-list>
                    </template>
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-4 ga-2">
                    <v-spacer />
                    <v-btn
                        color="#c2410c"
                        :disabled="studentV3TestSummaryPdfExporting || !completedStudentV3TestCount"
                        :loading="studentV3TestSummaryPdfExporting"
                        prepend-icon="mdi-file-pdf-box"
                        type="button"
                        variant="outlined"
                        @click="downloadStudentV3TestSummaryPdf">
                        PDF erstellen
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        @click="studentV3TestSummaryDialog = false">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { settings as loadSubjectPlanSettings } from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/SubjectOverviewJsonUploadController'
import { store as runV3StudentModuleTests } from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3StudentInformationController'
import { __invoke as downloadStudentV3TestSummaryPdf } from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3TestSummaryPdfController'
import loadStudentV3TestReadiness from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3TestReadinessController'
import { robotStudents as loadRobotStudents } from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentsTimetablesController'
import { useAdminStore } from '@/stores/admin/AdminStore'

const TESTS_V3_STUDENTS_PATH = '/admin/students-timetables/tests-v3/students'
const TESTS_V3_STUDENT_SELECTION_STORAGE_KEY_PREFIX = 'schooltool:students-timetables:tests-v3:selected-students'
const STUDENT_V3_TEST_BATCH_SIZE = 25
const STUDENT_V3_TEST_GROUPS = [
    { key: 'finished', color: 'success' },
    { key: 'negative', color: 'error' },
    { key: 'previous', color: 'warning' },
    { key: 'current', color: 'primary' },
    { key: 'additional', color: 'info' },
]
const STUDENT_V3_TEST_COMPARISON_GROUP_KEYS = ['finished', 'negative', 'previous', 'current', 'additional']
const STUDENT_V3_TEST_STATUS_PRESENTATIONS = {
    idle: {
        icon: 'mdi-circle-outline',
        color: 'grey-darken-1',
        title: 'Noch kein Test gestartet',
        'aria-label': 'Noch kein Test gestartet',
        'data-test-status': 'idle',
    },
    pending: {
        icon: 'mdi-clock-outline',
        color: 'warning',
        title: 'Wartet auf Test',
        'aria-label': 'Wartet auf Test',
        'data-test-status': 'pending',
    },
    running: {
        icon: 'mdi-progress-clock',
        color: 'primary',
        title: 'Test läuft',
        'aria-label': 'Test läuft',
        'data-test-status': 'running',
    },
    invalidData: {
        icon: 'mdi-database-alert',
        color: 'error',
        title: 'Falsche Daten – Test übersprungen',
        'aria-label': 'Falsche Daten – Test übersprungen',
        'data-test-status': 'invalid-data',
    },
    valid: {
        icon: 'mdi-check-circle',
        color: 'success',
        title: 'Test gültig',
        'aria-label': 'Test gültig',
        'data-test-status': 'valid',
    },
    failed: {
        icon: 'mdi-alert-circle',
        color: 'error',
        title: 'Test fehlgeschlagen',
        'aria-label': 'Test fehlgeschlagen',
        'data-test-status': 'failed',
    },
}
const STUDY_PROGRAM_DEFINITIONS = [
    {
        key: 'normalstudium',
        label: 'Normalstudium',
        color: 'primary',
        icon: 'mdi-calendar-range-outline',
    },
    {
        key: 'kompaktstudium',
        label: 'Kompaktstudium',
        color: 'teal-darken-2',
        icon: 'mdi-calendar-collapse-horizontal-outline',
    },
]
const testsV3Actions = ['students', 'tests']
const studentCollator = new Intl.Collator('de-AT', { numeric: true, sensitivity: 'base' })

export default {
    data() {
        return {
            testsV3Action: this.normalizedTestsV3Action(this.$route.params.subsection),
            students: [],
            selectedStudentKeys: [],
            studentsLoading: false,
            studentsError: false,
            testReadiness: null,
            testReadinessLoading: false,
            testReadinessError: false,
            studyPlanRows: {
                normalstudium: [],
                kompaktstudium: [],
            },
            studyPlansLoading: false,
            studyPlansLoaded: false,
            studyPlansError: '',
            expandedStudyPlans: [],
            studentV3TestResults: {},
            studentV3TestsRunning: false,
            studentV3TestSummaryDialog: false,
            studentV3TestSummaryPdfExporting: false,
            studentEmailCopyFeedback: {
                studentKey: '',
                status: '',
            },
            studentEmailCopyResetTimeout: null,
        }
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        personalSchoolyearLabel() {
            return this.config?.selected_schoolyear?.concerns
                || this.config?.selected_schoolyear?.name
                || 'nicht festgelegt'
        },
        testsV3NavigationItems() {
            return [
                {
                    key: 'students',
                    label: 'Studierende',
                    icon: 'mdi-account-school-outline',
                },
                {
                    key: 'tests',
                    label: 'Tests',
                    icon: 'mdi-test-tube',
                },
            ]
        },
        testReadinessReady() {
            return this.testReadiness?.ready === true
        },
        testReadinessIssues() {
            return Array.isArray(this.testReadiness?.issues) ? this.testReadiness.issues : []
        },
        sortedStudents() {
            return [...this.students].sort((firstStudent, secondStudent) => {
                const classComparison = studentCollator.compare(
                    this.studentClassLabel(firstStudent),
                    this.studentClassLabel(secondStudent),
                )

                if (classComparison !== 0) return classComparison

                const nameComparison = studentCollator.compare(
                    this.studentDisplayName(firstStudent),
                    this.studentDisplayName(secondStudent),
                )

                if (nameComparison !== 0) return nameComparison

                return studentCollator.compare(
                    this.studentSelectionKey(firstStudent),
                    this.studentSelectionKey(secondStudent),
                )
            })
        },
        studentSelectionKeys() {
            return this.sortedStudents
                .map(student => this.studentSelectionKey(student))
                .filter(Boolean)
        },
        studentClasses() {
            const studentKeysByClass = new Map()

            this.sortedStudents.forEach((student) => {
                const classLabel = this.studentClassLabel(student)
                const studentKey = this.studentSelectionKey(student)
                const studentKeys = studentKeysByClass.get(classLabel) || []

                if (studentKey) studentKeys.push(studentKey)

                studentKeysByClass.set(classLabel, studentKeys)
            })

            return [...studentKeysByClass.entries()].map(([classLabel, studentKeys]) => ({
                key: classLabel,
                label: classLabel,
                studentKeys: [...new Set(studentKeys)],
            }))
        },
        studentSemesters() {
            return Array.from({ length: 8 }, (_, index) => {
                const semester = index + 1
                const studentKeys = this.sortedStudents
                    .filter(student => Number(student?.semester) === semester)
                    .map(student => this.studentSelectionKey(student))
                    .filter(Boolean)

                return {
                    key: semester,
                    label: `${semester}. Semester`,
                    studentKeys: [...new Set(studentKeys)],
                }
            })
        },
        selectedStudentCount() {
            return this.selectedStudentKeys.length
        },
        selectedStudents() {
            const selectedStudentKeys = new Set(this.selectedStudentKeys)

            return this.sortedStudents.filter(student => selectedStudentKeys.has(this.studentSelectionKey(student)))
        },
        allStudentsSelected() {
            return this.studentSelectionKeys.length > 0
                && this.studentSelectionKeys.every(studentKey => this.selectedStudentKeys.includes(studentKey))
        },
        someStudentsSelected() {
            return this.selectedStudentCount > 0
        },
        showStudentV3TestProgress() {
            return this.studentV3TestsRunning
                || (this.studentV3TestSummaryDialog && this.completedStudentV3TestCount > 0)
        },
        completedStudentV3TestCount() {
            return this.selectedStudents.filter((student) => {
                const status = this.studentV3TestResult(student)?.status

                return status === 'complete' || status === 'error' || status === 'invalid_data'
            }).length
        },
        runningStudentV3TestCount() {
            return this.selectedStudents.filter(
                student => this.studentV3TestResult(student)?.status === 'running',
            ).length
        },
        studentV3TestProgressPercentage() {
            if (!this.selectedStudents.length) return 0

            return Math.round((this.completedStudentV3TestCount / this.selectedStudents.length) * 100)
        },
        failedStudentV3TestCount() {
            return this.selectedStudents.filter(
                student => this.studentV3TestStatusPresentation(student)['data-test-status'] === 'failed',
            ).length
        },
        invalidStudentV3TestCount() {
            return this.selectedStudents.filter(
                student => this.studentV3TestStatusPresentation(student)['data-test-status'] === 'invalid-data',
            ).length
        },
        testedStudentV3TestCount() {
            return Math.max(0, this.completedStudentV3TestCount - this.invalidStudentV3TestCount)
        },
        passedStudentV3TestCount() {
            return Math.max(
                0,
                this.testedStudentV3TestCount - this.failedStudentV3TestCount,
            )
        },
        failedStudentV3TestSummaries() {
            return this.selectedStudents
                .filter(
                    student => this.studentV3TestStatusPresentation(student)['data-test-status'] === 'failed',
                )
                .map((student) => {
                    const result = this.studentV3TestResult(student)
                    const failedGroupLabels = Array.isArray(result?.failedGroupLabels)
                        ? result.failedGroupLabels
                        : (Array.isArray(result?.groups) ? result.groups : [])
                            .filter(group => this.studentModuleGroupMatches(student, group.key) === false)
                            .map(group => String(group?.label || group?.key || '').trim())
                            .filter(Boolean)
                    const message = result?.status === 'error'
                        ? result.message || 'V3-Modulberechnung fehlgeschlagen.'
                        : `Abweichungen: ${failedGroupLabels.join(', ') || 'unbekannt'}`

                    return {
                        key: this.studentSelectionKey(student),
                        classLabel: this.studentClassLabel(student),
                        studentName: this.studentDisplayName(student),
                        message,
                    }
                })
        },
        invalidStudentV3TestSummaries() {
            return this.selectedStudents
                .filter(
                    student => this.studentV3TestStatusPresentation(student)['data-test-status'] === 'invalid-data',
                )
                .map(student => ({
                    key: this.studentSelectionKey(student),
                    classLabel: this.studentClassLabel(student),
                    studentName: this.studentDisplayName(student),
                    message: this.studentV3TestResult(student)?.message || 'Falscher Datensatz.',
                }))
        },
        studyPlanSections() {
            return STUDY_PROGRAM_DEFINITIONS.map((studyProgram) => {
                const semesters = new Map()
                const subjectRows = Array.isArray(this.studyPlanRows[studyProgram.key])
                    ? this.studyPlanRows[studyProgram.key]
                    : []

                subjectRows
                    .filter(subject => subject?.is_active !== false)
                    .forEach((subject, subjectIndex) => {
                        const semester = Number(subject?.semester)
                        const code = String(subject?.json_code || '').trim()

                        if (!Number.isInteger(semester) || semester < 1 || !code) return

                        const branch = String(subject?.branch || '').trim().toLocaleLowerCase('de-AT')
                        const branchLabel = {
                            gymnasial: 'GYM',
                            wirtschaftskundlich: 'WIKU',
                        }[branch] || ''
                        const modules = semesters.get(semester) || []

                        modules.push({
                            key: `${studyProgram.key}:${semester}:${code}:${branch || 'common'}:${subjectIndex}`,
                            code,
                            name: String(subject?.name || code).trim(),
                            branchLabel,
                        })
                        semesters.set(semester, modules)
                    })

                return {
                    ...studyProgram,
                    semesters: [...semesters.entries()]
                        .sort(([firstSemester], [secondSemester]) => firstSemester - secondSemester)
                        .map(([semester, modules]) => ({ semester, modules })),
                }
            })
        },
    },
    mounted() {
        if (this.redirectInvalidTestsV3Route()) return

        this.restoreStudentSelection()
        void this.loadTestReadiness()
        void this.loadStudents()

        if (this.testsV3Action === 'tests') {
            void this.loadStudyPlans()
        }
    },
    beforeUnmount() {
        this.clearStudentEmailCopyResetTimeout()
    },
    watch: {
        selectedStudentKeys() {
            this.persistStudentSelection()
        },
        '$route.params.subsection'(subsection) {
            this.testsV3Action = this.normalizedTestsV3Action(subsection)
            if (this.redirectInvalidTestsV3Route()) return

            if (this.testsV3Action === 'students') {
                void this.loadStudents()
            }

            if (this.testsV3Action === 'tests') {
                void this.loadTestReadiness(true)
                void this.loadStudyPlans()
            }
        },
    },
    methods: {
        normalizedTestsV3Action(subsection) {
            return testsV3Actions.includes(subsection) ? subsection : 'students'
        },
        handleTestsV3Navigation(key) {
            this.testsV3Action = this.normalizedTestsV3Action(key)
            this.$router.push({ path: `/admin/students-timetables/tests-v3/${this.testsV3Action}` })
        },
        studentSelectionStorageKey() {
            const schoolyearId = this.config?.selected_schoolyear?.id || 'default'

            return `${TESTS_V3_STUDENT_SELECTION_STORAGE_KEY_PREFIX}:${schoolyearId}`
        },
        studentSelectionStorage() {
            if (typeof window === 'undefined' || !window.localStorage) return null

            return window.localStorage
        },
        persistStudentSelection() {
            try {
                const studentKeys = [...new Set(
                    this.selectedStudentKeys
                        .map(studentKey => String(studentKey || '').trim())
                        .filter(Boolean),
                )]

                this.studentSelectionStorage()?.setItem(
                    this.studentSelectionStorageKey(),
                    JSON.stringify(studentKeys),
                )
            } catch {
                // Ignore unavailable or full browser storage.
            }
        },
        restoreStudentSelection() {
            try {
                const storedStudentKeys = JSON.parse(
                    this.studentSelectionStorage()?.getItem(this.studentSelectionStorageKey()) || '[]',
                )

                this.selectedStudentKeys = [...new Set(
                    (Array.isArray(storedStudentKeys) ? storedStudentKeys : [])
                        .map(studentKey => String(studentKey || '').trim())
                        .filter(Boolean),
                )]
            } catch {
                this.selectedStudentKeys = []
            }
        },
        studentSelectionKey(student) {
            const studentCode = String(student?.student_code || student?.studentCode || '').trim()

            if (studentCode) return studentCode

            const studentId = String(student?.id || '').trim()

            return studentId ? `id:${studentId}` : ''
        },
        studentClassLabel(student) {
            return String(student?.class || '').trim() || '–'
        },
        studentDisplayName(student) {
            const lastName = String(student?.last_name || student?.lastName || '').trim()
            const firstName = String(student?.first_name || student?.firstName || '').trim()

            return [lastName, firstName].filter(Boolean).join(' ') || 'Unbekannt'
        },
        studentEmail(student) {
            return String(student?.email || '').trim()
        },
        studentEmailCopyStatus(student) {
            if (this.studentEmailCopyFeedback.studentKey !== this.studentSelectionKey(student)) return ''

            return this.studentEmailCopyFeedback.status
        },
        studentEmailCopyTitle(student) {
            const email = this.studentEmail(student)
            const status = this.studentEmailCopyStatus(student)

            if (status === 'copied') return `E-Mail kopiert: ${email}`
            if (status === 'failed') return `Kopieren fehlgeschlagen. E-Mail erneut kopieren: ${email}`

            return `E-Mail kopieren: ${email}`
        },
        async copyStudentEmail(student) {
            const email = this.studentEmail(student)
            const clipboard = globalThis.navigator?.clipboard

            if (!email || !clipboard?.writeText) {
                this.setStudentEmailCopyFeedback(student, 'failed')
                return false
            }

            try {
                await clipboard.writeText(email)
                this.setStudentEmailCopyFeedback(student, 'copied')

                return true
            } catch {
                this.setStudentEmailCopyFeedback(student, 'failed')

                return false
            }
        },
        setStudentEmailCopyFeedback(student, status) {
            this.clearStudentEmailCopyResetTimeout()
            this.studentEmailCopyFeedback = {
                studentKey: this.studentSelectionKey(student),
                status,
            }
            this.studentEmailCopyResetTimeout = globalThis.setTimeout(() => {
                this.studentEmailCopyFeedback = {
                    studentKey: '',
                    status: '',
                }
                this.studentEmailCopyResetTimeout = null
            }, 1500)
        },
        clearStudentEmailCopyResetTimeout() {
            if (this.studentEmailCopyResetTimeout === null) return

            globalThis.clearTimeout(this.studentEmailCopyResetTimeout)
            this.studentEmailCopyResetTimeout = null
        },
        studentReligionLabel(student) {
            return String(student?.religion || '').trim()
        },
        studentStudySelectionLabels(student) {
            const selection = student?.study_selection || student?.studySelection || {}
            const branch = String(selection.branch || '').trim().toLocaleLowerCase('de-AT')
            const branchLabel = {
                wirtschaftskundlich: 'WIKU',
                gymnasial: 'GYM',
            }[branch] || ''

            return [
                String(selection.religion || '').trim().toLocaleUpperCase('de-AT'),
                String(selection.language || '').trim().toLocaleUpperCase('de-AT'),
                branchLabel,
                String(selection.arts_subject || selection.artsSubject || '').trim().toLocaleUpperCase('de-AT'),
            ].filter(Boolean)
        },
        studentStudyProgramKey(student) {
            const studyProgram = String(student?.study_program || student?.studyProgram || '')
                .trim()
                .toLocaleLowerCase('de-AT')
            const instructionType = String(student?.instruction_type || student?.instructionType || '')
                .trim()
                .toLocaleLowerCase('de-AT')

            if (studyProgram === 'kompaktstudium' || instructionType === 'kompaktunterricht') {
                return 'kompaktstudium'
            }

            if (studyProgram === 'normalstudium' || instructionType === 'normalunterricht') {
                return 'normalstudium'
            }

            return ''
        },
        studentSemesterLabel(student) {
            const studyProgramAbbreviation = {
                normalstudium: 'N',
                kompaktstudium: 'K',
            }[this.studentStudyProgramKey(student)] || ''
            const semester = String(student?.semester ?? '').trim()

            return [studyProgramAbbreviation, semester].filter(Boolean).join(' ')
        },
        studentExpectedModules(student) {
            const modules = student?.expected_modules ?? student?.expectedModules

            if (!Array.isArray(modules)) return null

            return modules
                .map((module, moduleIndex) => ({
                    key: `${this.studentSelectionKey(student)}:${String(module?.code || '').trim()}:${moduleIndex}`,
                    code: String(module?.code || '').trim(),
                    name: String(module?.name || module?.code || '').trim(),
                    semester: Number(module?.semester),
                }))
                .filter(module => module.code)
        },
        studentExpectedAdditionalModules(student) {
            const modules = student?.expected_additional_modules ?? student?.expectedAdditionalModules

            if (!Array.isArray(modules)) return null

            return modules
                .map((module, moduleIndex) => ({
                    key: `${this.studentSelectionKey(student)}:additional:${String(module?.code || '').trim()}:${moduleIndex}`,
                    code: String(module?.code || '').trim(),
                    name: String(module?.name || module?.code || '').trim(),
                    semester: Number(module?.semester),
                }))
                .filter(module => module.code)
        },
        studentSchoolLevelLabel(student) {
            const schoolLevel = String(student?.school_level || student?.schoolLevel || '')
                .trim()
                .replace(/[.\s-]+/gu, '_')
            const attendanceYear = String(student?.attendance_year || student?.attendanceYear || '').trim()

            return schoolLevel && attendanceYear && !schoolLevel.includes('_')
                ? `${schoolLevel}_${attendanceYear}`
                : schoolLevel
        },
        studentDataQualityIssues(student) {
            const issues = student?.data_quality_issues ?? student?.dataQualityIssues

            if (!Array.isArray(issues)) return []

            return issues
                .map(issue => String(typeof issue === 'string' ? issue : issue?.message || '').trim())
                .filter(Boolean)
        },
        studentCourseResultLabels(student, group) {
            return this.studentCourseResultItems(student, group)
                .map(({ code, grade }) => `${code} (${grade})`)
        },
        studentCourseResultItems(student, group) {
            const courseResults = student?.course_results || student?.courseResults || {}
            const results = Array.isArray(courseResults[group]) ? courseResults[group] : []

            return results
                .map((result, index) => {
                    const code = String(result?.code || '').trim()
                    const grade = String(result?.grade || '').trim().toLocaleUpperCase('de-AT')

                    return code && grade
                        ? { code, grade, key: `${code}|${grade}|${index}` }
                        : null
                })
                .filter(Boolean)
        },
        studentSexPresentation(student) {
            const sex = String(student?.sex || '').trim().toLocaleLowerCase('de-AT')

            if (sex === 'm') {
                return { icon: 'mdi-gender-male', color: 'blue', label: 'männlich' }
            }

            if (sex === 'w') {
                return { icon: 'mdi-gender-female', color: 'pink', label: 'weiblich' }
            }

            return null
        },
        isStudentSelected(student) {
            const studentKey = this.studentSelectionKey(student)

            return Boolean(studentKey) && this.selectedStudentKeys.includes(studentKey)
        },
        toggleStudentSelection(student, isSelected) {
            const studentKey = this.studentSelectionKey(student)

            if (!studentKey) return

            if (isSelected) {
                this.selectedStudentKeys = [...new Set([...this.selectedStudentKeys, studentKey])]

                return
            }

            this.selectedStudentKeys = this.selectedStudentKeys.filter(selectedKey => selectedKey !== studentKey)
        },
        areStudentKeysSelected(studentKeys) {
            return studentKeys.length > 0
                && studentKeys.every(studentKey => this.selectedStudentKeys.includes(studentKey))
        },
        toggleStudentKeysSelection(studentKeys) {
            const selectedStudentKeys = new Set(studentKeys)

            if (this.areStudentKeysSelected(studentKeys)) {
                this.selectedStudentKeys = this.selectedStudentKeys.filter(
                    studentKey => !selectedStudentKeys.has(studentKey),
                )

                return
            }

            this.selectedStudentKeys = [...new Set([...this.selectedStudentKeys, ...studentKeys])]
        },
        isStudentClassSelected(studentClass) {
            return this.areStudentKeysSelected(studentClass.studentKeys)
        },
        toggleStudentClassSelection(studentClass) {
            this.toggleStudentKeysSelection(studentClass.studentKeys)
        },
        isStudentSemesterSelected(studentSemester) {
            return this.areStudentKeysSelected(studentSemester.studentKeys)
        },
        toggleStudentSemesterSelection(studentSemester) {
            this.toggleStudentKeysSelection(studentSemester.studentKeys)
        },
        selectAllStudents() {
            this.selectedStudentKeys = [...this.studentSelectionKeys]
        },
        clearStudentSelection() {
            this.selectedStudentKeys = []
        },
        toggleAllStudents(isSelected) {
            if (isSelected) {
                this.selectAllStudents()

                return
            }

            this.clearStudentSelection()
        },
        studentV3TestResult(student) {
            const studentKey = this.studentSelectionKey(student)

            return studentKey ? this.studentV3TestResults[studentKey] || null : null
        },
        studentV3TestStatusPresentation(student) {
            const result = this.studentV3TestResult(student)

            if (!result) return STUDENT_V3_TEST_STATUS_PRESENTATIONS.idle
            if (result.status === 'pending') return STUDENT_V3_TEST_STATUS_PRESENTATIONS.pending
            if (result.status === 'running') return STUDENT_V3_TEST_STATUS_PRESENTATIONS.running
            if (result.status === 'invalid_data') return STUDENT_V3_TEST_STATUS_PRESENTATIONS.invalidData

            if (result.status === 'complete') {
                const isValid = typeof result.isValid === 'boolean'
                    ? result.isValid
                    : STUDENT_V3_TEST_COMPARISON_GROUP_KEYS.every(
                        groupKey => this.studentModuleGroupMatches(student, groupKey) === true,
                    )

                return isValid
                    ? STUDENT_V3_TEST_STATUS_PRESENTATIONS.valid
                    : STUDENT_V3_TEST_STATUS_PRESENTATIONS.failed
            }

            return STUDENT_V3_TEST_STATUS_PRESENTATIONS.failed
        },
        setStudentV3TestResult(student, result) {
            const studentKey = this.studentSelectionKey(student)

            if (!studentKey) return

            this.setStudentV3TestResults({ [studentKey]: result })
        },
        setStudentV3TestResults(resultsByStudentKey) {
            if (!Object.keys(resultsByStudentKey).length) return

            this.studentV3TestResults = {
                ...this.studentV3TestResults,
                ...resultsByStudentKey,
            }
        },
        normalizedStudentV3TestGroups(moduleGroups) {
            const groupsByKey = new Map(
                (Array.isArray(moduleGroups) ? moduleGroups : [])
                    .map(group => [String(group?.key || '').trim(), group]),
            )
            const missingGroup = STUDENT_V3_TEST_GROUPS.find(group => !groupsByKey.has(group.key))

            if (missingGroup) {
                throw new Error(`Die V3-Gruppe ${missingGroup.key} fehlt.`)
            }

            return STUDENT_V3_TEST_GROUPS.map((groupDefinition) => {
                const group = groupsByKey.get(groupDefinition.key)
                const modules = (Array.isArray(group?.modules) ? group.modules : [])
                    .map(module => ({
                        code: String(module?.code || '').trim(),
                        name: String(module?.name || '').trim(),
                    }))
                    .filter(module => module.code)
                const reportedCount = Number(group?.count)

                if (Number.isFinite(reportedCount) && reportedCount !== modules.length) {
                    throw new Error(`Der V3-Count für ${groupDefinition.key} ist inkonsistent.`)
                }

                return {
                    ...groupDefinition,
                    label: String(group?.label || groupDefinition.key).trim(),
                    count: modules.length,
                    modules,
                }
            })
        },
        normalizedStudentModuleComparisonCode(code) {
            const normalizedCode = String(code || '').trim().toLocaleUpperCase('de-AT')
            const codeParts = normalizedCode.match(/^(.*?)(\d*)$/u)
            const base = codeParts?.[1] || ''
            const moduleNumber = codeParts?.[2] || ''
            const canonicalBase = {
                GPB: 'GS',
                GWB: 'GW',
                LET: 'LPT',
                MU: 'ME',
                REV: 'R',
                RIS: 'R',
                RK: 'R',
                ROR: 'R',
                SPA: 'S',
            }[base] || base
            const canonicalModuleNumber = canonicalBase === 'LPT' && moduleNumber === '1'
                ? ''
                : moduleNumber

            return `${canonicalBase}${canonicalModuleNumber}`
        },
        normalizedStudentModuleCodes(modules) {
            return [...new Set(
                (Array.isArray(modules) ? modules : [])
                    .map(module => this.normalizedStudentModuleComparisonCode(module?.code))
                    .filter(Boolean),
            )].sort(studentCollator.compare)
        },
        studentModuleGroupMatches(student, moduleGroupKey) {
            const result = this.studentV3TestResult(student)

            if (result?.status !== 'complete' || !this.studentModuleGroupCanCompare(student, moduleGroupKey)) {
                return null
            }

            const moduleGroup = result.groups.find(group => group.key === moduleGroupKey)

            return result.sourceStudent === student && typeof moduleGroup?.comparison?.matches === 'boolean'
                ? moduleGroup.comparison.matches
                : this.studentModuleGroupComparison(student, moduleGroup).matches
        },
        studentModuleGroupCanCompare(student, moduleGroupKey) {
            if (['finished', 'negative'].includes(moduleGroupKey)) return true

            if (['previous', 'current'].includes(moduleGroupKey)) {
                return this.studentExpectedModules(student) !== null
                    && Number.isInteger(Number(student?.semester))
            }

            if (moduleGroupKey === 'additional') {
                return this.studentExpectedAdditionalModules(student) !== null
            }

            return false
        },
        studentModuleGroupExpectedModules(student, moduleGroupKey) {
            const snapshotGroupKey = {
                finished: 'completed',
                negative: 'negative',
            }[moduleGroupKey]
            let sourceModules = []

            if (snapshotGroupKey) {
                sourceModules = this.studentCourseResultItems(student, snapshotGroupKey)

                if (snapshotGroupKey === 'negative') {
                    const completedModuleCodes = new Set(
                        this.studentCourseResultItems(student, 'completed')
                            .map(module => this.normalizedStudentModuleComparisonCode(module.code)),
                    )

                    sourceModules = sourceModules.filter(module => !completedModuleCodes.has(
                        this.normalizedStudentModuleComparisonCode(module.code),
                    ))
                }
            } else if (['previous', 'current'].includes(moduleGroupKey)) {
                const currentSemester = Number(student?.semester)
                const expectedSemesterModules = this.studentExpectedModules(student)

                if (expectedSemesterModules === null || !Number.isInteger(currentSemester)) return []

                sourceModules = expectedSemesterModules.filter((module) => {
                    if (moduleGroupKey === 'previous') return module.semester < currentSemester

                    return module.semester === currentSemester
                })
            } else if (moduleGroupKey === 'additional') {
                const currentSemester = Number(student?.semester)
                const expectedProgressionModules = this.studentExpectedAdditionalModules(student)

                if (expectedProgressionModules === null) return []

                sourceModules = Number.isInteger(currentSemester)
                    ? expectedProgressionModules.filter(module => module.semester > currentSemester)
                    : expectedProgressionModules
            } else {
                return []
            }

            const expectedModulesByCode = new Map()

            sourceModules.forEach((module) => {
                const normalizedCode = this.normalizedStudentModuleComparisonCode(module.code)

                if (!normalizedCode || expectedModulesByCode.has(normalizedCode)) return

                expectedModulesByCode.set(normalizedCode, {
                    key: `expected:${moduleGroupKey}:${normalizedCode}`,
                    code: module.code,
                    grade: module.grade,
                    normalizedCode,
                    title: module.grade
                        ? `Soll-Modul ${module.code} (${module.grade})`
                        : module.name || `Soll-Modul ${module.code}`,
                })
            })

            return [...expectedModulesByCode.values()]
                .sort((leftModule, rightModule) => studentCollator.compare(
                    leftModule.normalizedCode,
                    rightModule.normalizedCode,
                ))
        },
        studentModuleGroupMismatches(student, moduleGroupKey) {
            const result = this.studentV3TestResult(student)

            if (result?.status !== 'complete' || !this.studentModuleGroupCanCompare(student, moduleGroupKey)) {
                return []
            }

            const moduleGroup = result.groups.find(group => group.key === moduleGroupKey)
            const cachedMismatches = moduleGroup?.comparison?.mismatches

            return result.sourceStudent === student && Array.isArray(cachedMismatches)
                ? cachedMismatches
                : this.studentModuleGroupComparison(student, moduleGroup).mismatches
        },
        studentModuleGroupComparison(student, moduleGroup) {
            const moduleGroupKey = String(moduleGroup?.key || '').trim()
            const expectedModules = this.studentModuleGroupExpectedModules(student, moduleGroupKey)

            if (!this.studentModuleGroupCanCompare(student, moduleGroupKey)) {
                return { expectedModules, mismatches: [], matches: null }
            }

            const expectedModuleCodes = new Set(expectedModules.map(module => module.normalizedCode))
            const testResultModulesByCode = new Map()
            const testResultModules = Array.isArray(moduleGroup?.modules) ? moduleGroup.modules : []

            testResultModules.forEach((module) => {
                const normalizedCode = this.normalizedStudentModuleComparisonCode(module.code)

                if (!normalizedCode || testResultModulesByCode.has(normalizedCode)) return

                testResultModulesByCode.set(normalizedCode, {
                    key: `actual:${moduleGroupKey}:${normalizedCode}`,
                    code: module.code,
                    normalizedCode,
                    title: module.name
                        ? `Zusätzlich im V3-Ergebnis: ${module.name}`
                        : 'Zusätzlich im V3-Ergebnis',
                })
            })

            const testResultModuleCodes = new Set(testResultModulesByCode.keys())
            const missingModules = expectedModules
                .filter(module => !testResultModuleCodes.has(module.normalizedCode))
                .map(module => ({
                    ...module,
                    key: `missing:${moduleGroupKey}:${module.normalizedCode}`,
                    title: 'Fehlt im V3-Ergebnis',
                }))
            const additionalModules = [...testResultModulesByCode.values()]
                .filter(module => !expectedModuleCodes.has(module.normalizedCode))
            const mismatches = [...missingModules, ...additionalModules]
                .sort((leftModule, rightModule) => studentCollator.compare(
                    leftModule.normalizedCode,
                    rightModule.normalizedCode,
                ))

            return {
                expectedModules,
                mismatches,
                matches: mismatches.length === 0,
            }
        },
        completedStudentV3TestResult(student, groups) {
            const groupsWithComparisons = groups.map(group => ({
                ...group,
                comparison: this.studentModuleGroupComparison(student, group),
            }))
            const failedGroupLabels = groupsWithComparisons
                .filter(group => group.comparison.matches === false)
                .map(group => group.label)

            return {
                status: 'complete',
                message: '',
                groups: groupsWithComparisons,
                isValid: groupsWithComparisons.every(group => group.comparison.matches === true),
                failedGroupLabels,
                sourceStudent: student,
            }
        },
        studentFinishedModulesMatch(student) {
            return this.studentModuleGroupMatches(student, 'finished')
        },
        studentNegativeModulesMatch(student) {
            return this.studentModuleGroupMatches(student, 'negative')
        },
        studentV3TestSummaryPdfPayload() {
            const failedSummaries = new Map(
                this.failedStudentV3TestSummaries.map(summary => [summary.key, summary]),
            )
            const invalidSummaries = new Map(
                this.invalidStudentV3TestSummaries.map(summary => [summary.key, summary]),
            )

            return {
                results: this.selectedStudents
                    .map((student) => {
                        const key = this.studentSelectionKey(student)
                        const status = this.studentV3TestStatusPresentation(student)['data-test-status']
                        const summary = status === 'invalid-data'
                            ? invalidSummaries.get(key)
                            : failedSummaries.get(key)

                        if (!['valid', 'failed', 'invalid-data'].includes(status)) return null

                        return {
                            status: {
                                valid: 'passed',
                                failed: 'failed',
                                'invalid-data': 'invalid_data',
                            }[status],
                            class_label: this.studentClassLabel(student),
                            student_name: this.studentDisplayName(student),
                            message: summary?.message || '',
                        }
                    })
                    .filter(Boolean),
            }
        },
        async downloadStudentV3TestSummaryPdf() {
            if (this.studentV3TestSummaryPdfExporting || !this.completedStudentV3TestCount) return

            this.studentV3TestSummaryPdfExporting = true

            try {
                const response = await axios.post(
                    downloadStudentV3TestSummaryPdf.url(),
                    this.studentV3TestSummaryPdfPayload(),
                    { responseType: 'blob' },
                )
                const blob = response.data instanceof Blob
                    ? response.data
                    : new Blob([response.data], { type: 'application/pdf' })
                const filename = this.fileNameFromContentDisposition(response?.headers?.['content-disposition'])
                    || 'stundenplan-v3-testzusammenfassung.pdf'

                this.downloadBlob(blob, filename)
            } catch (error) {
                console.error(error)
                window.alert?.('Das PDF konnte nicht erstellt werden.')
            } finally {
                this.studentV3TestSummaryPdfExporting = false
            }
        },
        downloadBlob(blob, filename) {
            const objectUrl = URL.createObjectURL(blob)
            const link = document.createElement('a')

            link.href = objectUrl
            link.download = filename
            document.body.appendChild(link)
            link.click()
            link.remove()
            URL.revokeObjectURL(objectUrl)
        },
        fileNameFromContentDisposition(headerValue) {
            const normalizedHeader = String(headerValue || '').trim()
            if (!normalizedHeader) return ''

            const utf8Match = normalizedHeader.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
            if (utf8Match?.[1]) {
                try {
                    return decodeURIComponent(utf8Match[1]).replace(/["']/g, '').trim()
                } catch {
                    return utf8Match[1].replace(/["']/g, '').trim()
                }
            }

            const plainMatch = normalizedHeader.match(/filename\s*=\s*"?(?<file>[^";]+)"?/i)

            return plainMatch?.groups?.file?.trim() || ''
        },
        async runStudentV3TestBatch(students) {
            const studentsByCode = new Map()
            const initialResultsByStudentKey = {}

            students.forEach((student) => {
                const studentKey = this.studentSelectionKey(student)
                const dataQualityIssues = this.studentDataQualityIssues(student)

                if (dataQualityIssues.length) {
                    if (!studentKey) return

                    initialResultsByStudentKey[studentKey] = {
                        status: 'invalid_data',
                        message: dataQualityIssues.join(' '),
                        groups: [],
                    }

                    return
                }

                const studentCode = String(student?.student_code || student?.studentCode || '').trim()

                if (!studentCode) {
                    if (!studentKey) return

                    initialResultsByStudentKey[studentKey] = {
                        status: 'error',
                        message: 'Keine Schülerkennzahl vorhanden.',
                        groups: [],
                    }

                    return
                }

                studentsByCode.set(studentCode, student)
                initialResultsByStudentKey[studentKey] = { status: 'running', message: '', groups: [] }
            })

            this.setStudentV3TestResults(initialResultsByStudentKey)

            if (!studentsByCode.size) return

            try {
                const response = await axios.post(runV3StudentModuleTests.url(), {
                    student_codes: [...studentsByCode.keys()],
                })
                const results = Array.isArray(response.data?.data) ? response.data.data : []
                const resultsByStudentCode = new Map(results.map(result => [
                    String(result?.student_code || '').trim(),
                    result,
                ]))
                const completedResultsByStudentKey = {}

                studentsByCode.forEach((student, studentCode) => {
                    const studentKey = this.studentSelectionKey(student)

                    if (!studentKey) return

                    try {
                        const result = resultsByStudentCode.get(studentCode)

                        if (!result) throw new Error('Das V3-Batchergebnis fehlt.')

                        const groups = this.normalizedStudentV3TestGroups(result.module_selection_groups)

                        completedResultsByStudentKey[studentKey] = this.completedStudentV3TestResult(student, groups)
                    } catch {
                        completedResultsByStudentKey[studentKey] = {
                            status: 'error',
                            message: 'V3-Modulberechnung fehlgeschlagen.',
                            groups: [],
                        }
                    }
                })

                this.setStudentV3TestResults(completedResultsByStudentKey)
            } catch {
                const failedResultsByStudentKey = {}

                studentsByCode.forEach((student) => {
                    const studentKey = this.studentSelectionKey(student)

                    if (!studentKey) return

                    failedResultsByStudentKey[studentKey] = {
                        status: 'error',
                        message: 'V3-Modulberechnung fehlgeschlagen.',
                        groups: [],
                    }
                })

                this.setStudentV3TestResults(failedResultsByStudentKey)
            }
        },
        async runTests() {
            if (this.studentV3TestsRunning || !this.selectedStudents.length) return

            await this.loadTestReadiness(true)
            if (!this.testReadinessReady) return

            const students = [...this.selectedStudents]
            this.studentV3TestSummaryDialog = false
            this.studentV3TestResults = Object.fromEntries(students.map(student => [
                this.studentSelectionKey(student),
                { status: 'pending', message: '', groups: [] },
            ]))
            this.studentV3TestsRunning = true

            try {
                for (let index = 0; index < students.length; index += STUDENT_V3_TEST_BATCH_SIZE) {
                    await this.runStudentV3TestBatch(students.slice(index, index + STUDENT_V3_TEST_BATCH_SIZE))
                }
            } finally {
                this.studentV3TestsRunning = false
                this.studentV3TestSummaryDialog = true
            }
        },
        async loadStudents() {
            if (this.students.length || this.studentsLoading) return

            this.studentsLoading = true
            this.studentsError = false

            try {
                const response = await axios.get(loadRobotStudents.url())
                this.students = Array.isArray(response.data?.data) ? response.data.data : []

                const availableStudentKeys = new Set(
                    this.students
                        .map(student => this.studentSelectionKey(student))
                        .filter(Boolean),
                )
                this.selectedStudentKeys = this.selectedStudentKeys.filter(studentKey => availableStudentKeys.has(studentKey))
            } catch {
                this.students = []
                this.selectedStudentKeys = []
                this.studentsError = true
            } finally {
                this.studentsLoading = false
            }
        },
        async loadTestReadiness(force = false) {
            if (this.testReadinessLoading || (!force && this.testReadiness)) return

            this.testReadinessLoading = true
            this.testReadinessError = false

            try {
                const response = await axios.get(loadStudentV3TestReadiness.url())
                this.testReadiness = response.data?.data || null
                this.testReadinessError = !this.testReadiness
            } catch {
                this.testReadiness = null
                this.testReadinessError = true
            } finally {
                this.testReadinessLoading = false
            }
        },
        async loadStudyPlans() {
            if (this.studyPlansLoading || this.studyPlansLoaded) return

            this.studyPlansLoading = true
            this.studyPlansError = ''

            try {
                const responses = await Promise.all(STUDY_PROGRAM_DEFINITIONS.map(studyProgram => axios.get(
                    loadSubjectPlanSettings.url(
                        { studyProgram: studyProgram.key },
                        {
                            query: {
                                schoolyear_scope: 'personal',
                                subjects_only: true,
                            },
                        },
                    ),
                )))

                this.studyPlanRows = Object.fromEntries(STUDY_PROGRAM_DEFINITIONS.map((studyProgram, index) => [
                    studyProgram.key,
                    Array.isArray(responses[index].data?.data?.subjects)
                        ? responses[index].data.data.subjects
                        : [],
                ]))
                this.studyPlansLoaded = true
            } catch {
                this.studyPlanRows = {
                    normalstudium: [],
                    kompaktstudium: [],
                }
                this.studyPlansError = 'Die Fachpläne konnten nicht geladen werden.'
            } finally {
                this.studyPlansLoading = false
            }
        },
        redirectInvalidTestsV3Route() {
            if (
                this.$route.params.section !== 'tests-v3'
                || testsV3Actions.includes(this.$route.params.subsection)
            ) {
                return false
            }

            this.testsV3Action = 'students'
            this.$router.replace({ path: TESTS_V3_STUDENTS_PATH })

            return true
        },
    },
}
</script>

<style scoped>
.tests-v3-page__title-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: baseline;
    margin-bottom: 16px;
}

.tests-v3-page__title {
    margin: 0;
    color: #1e2433;
}

.tests-v3-page__schoolyear {
    font-size: 1rem;
    white-space: nowrap;
}

.tests-v3-subnav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px;
    margin-bottom: 16px;
    border: 1px solid rgba(148, 163, 184, 0.24);
    background: rgba(255, 255, 255, 0.88);
}

.tests-v3-students {
    overflow: hidden;
}

.tests-v3-study-plans {
    margin-bottom: 16px;
    overflow: hidden;
}

.tests-v3-study-plans__header {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.24);
}

.tests-v3-study-plans__title {
    color: #1e2433;
    font-weight: 800;
}

.tests-v3-study-plans__content {
    padding: 0;
}

.tests-v3-study-plan {
    min-width: 0;
}

.tests-v3-study-plan + .tests-v3-study-plan {
    border-top: 1px solid rgba(148, 163, 184, 0.24);
}

.tests-v3-study-plan__title {
    padding: 16px 20px;
}

.tests-v3-study-plan :deep(.v-expansion-panel-text__wrapper) {
    padding: 0 20px 20px;
}

.tests-v3-study-plan__heading {
    display: flex;
    gap: 9px;
    align-items: center;
}

.tests-v3-study-plan__heading h3 {
    margin: 0;
    color: #1e2433;
    font-size: 1rem;
    font-weight: 800;
}

.tests-v3-study-plan__semesters {
    overflow: hidden;
    border: 1px solid #dbe3ee;
    border-radius: 10px;
}

.tests-v3-study-plan__semester {
    display: grid;
    grid-template-columns: minmax(100px, 128px) minmax(0, 1fr);
    gap: 14px;
    align-items: start;
    padding: 11px 13px;
    background: #fff;
}

.tests-v3-study-plan__semester:nth-child(even) {
    background: #f8fafc;
}

.tests-v3-study-plan__semester + .tests-v3-study-plan__semester {
    border-top: 1px solid #e2e8f0;
}

.tests-v3-study-plan__semester-label {
    padding-top: 4px;
    color: #334155;
    font-size: 0.82rem;
    font-weight: 800;
    white-space: nowrap;
}

.tests-v3-study-plan__modules {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
}

.tests-v3-students__header {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.24);
}

.tests-v3-students__title {
    color: #1e2433;
    font-weight: 800;
}

.tests-v3-students__actions {
    justify-content: flex-end;
}

.tests-v3-students__classes {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.24);
}

.tests-v3-students__selection-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.tests-v3-students__classes-label {
    color: #475569;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.tests-v3-students__class-chips,
.tests-v3-students__semester-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tests-v3-students__class-chip,
.tests-v3-students__semester-button {
    font-weight: 800;
}

.tests-v3-students__table :deep(th) {
    color: #475569;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.tests-v3-students__table :deep(table) {
    width: 100%;
    table-layout: fixed;
}

.tests-v3-students__table :deep(th),
.tests-v3-students__table :deep(td) {
    padding-inline: 10px !important;
    overflow-wrap: anywhere;
}

.tests-v3-students__selection-column {
    width: 5%;
}

.tests-v3-students__class-column {
    width: 5%;
}

.tests-v3-students__name-column {
    width: 21%;
}

.tests-v3-students__study-selection-column {
    width: 13%;
}

.tests-v3-students__semester-column {
    width: 8%;
}

.tests-v3-students__course-results-column {
    width: 24%;
}

.tests-v3-students__semester {
    color: #334155;
    font-size: 0.82rem;
}

.tests-v3-students__semester-main {
    font-weight: 800;
}

.tests-v3-students__semester-school-level {
    margin-left: 4px;
    font-weight: 400;
}

.tests-v3-students__course-results {
    color: #334155;
    font-size: 0.82rem;
    font-weight: 400;
    line-height: 1.5;
}

.tests-v3-students__course-result {
    overflow-wrap: anywhere;
}

.tests-v3-students__course-result:not(:last-child)::after {
    content: ', ';
}

.tests-v3-students__course-result-grade {
    font-weight: 700;
}

.tests-v3-students__course-result-grade--completed {
    color: rgb(var(--v-theme-success));
}

.tests-v3-students__course-result-grade--negative {
    color: rgb(var(--v-theme-error));
}

.tests-v3-students__name {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 7px;
    align-items: center;
    max-width: 100%;
}

.tests-v3-students__identity {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.tests-v3-students__email {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    width: fit-content;
    max-width: 100%;
    padding: 0;
    border: 0;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 400;
    line-height: 1.35;
    text-align: left;
}

.tests-v3-students__email span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tests-v3-students__email:hover,
.tests-v3-students__email:focus-visible {
    color: rgb(var(--v-theme-primary));
}

.tests-v3-students__email--copied {
    color: rgb(var(--v-theme-success));
}

.tests-v3-students__email--failed {
    color: rgb(var(--v-theme-error));
}

.tests-v3-students__religion {
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
}

.tests-v3-students__study-selection {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.tests-v3-students__study-selection-item {
    padding: 2px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: 0.76rem;
    font-weight: 800;
    line-height: 1.35;
}

.tests-v3-students__row--selected {
    background: #eef2ff;
}

.tests-v3-students__row--invalid-data {
    background: #fff1f2;
}

.tests-v3-students__data-quality-error {
    display: grid;
    gap: 5px;
    margin-top: 7px;
}

.tests-v3-students__data-quality-error :deep(.v-chip) {
    justify-self: start;
}

.tests-v3-students__data-quality-message {
    color: rgb(var(--v-theme-error));
    font-size: 0.74rem;
    font-weight: 700;
    line-height: 1.35;
}

.tests-v3-selection__header {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.24);
}

.tests-v3-selection__progress {
    display: grid;
    gap: 7px;
    padding: 10px 20px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.18);
    background: #f8fafc;
    color: #475569;
}

.tests-v3-selection__progress-summary {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.tests-v3-selection__title {
    color: #1e2433;
    font-weight: 800;
}

.tests-v3-selection__table :deep(.tests-v3-students__row--selected) {
    background: #eef2ff;
}

.tests-v3-selection__table :deep(.tests-v3-students__row--invalid-data) {
    background: #fff1f2;
}

.tests-v3-selection__table :deep(.tests-v3-selection__module-test-row) {
    background: #f1f5f9;
}

.tests-v3-selection__module-test {
    padding-block: 12px !important;
    border-bottom: 2px solid rgba(148, 163, 184, 0.28) !important;
    vertical-align: top;
}

.tests-v3-selection__module-test-title {
    margin-bottom: 8px;
    color: #1e3a8a;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.tests-v3-selection__test-status {
    display: flex;
    gap: 7px;
    align-items: center;
    min-height: 32px;
    font-size: 0.82rem;
    font-weight: 700;
}

.tests-v3-selection__invalid-data-status {
    display: flex;
    gap: 9px;
    align-items: flex-start;
    padding: 10px 12px;
    border: 1px solid rgba(var(--v-theme-error), 0.28);
    border-radius: 8px;
    background: rgba(var(--v-theme-error), 0.08);
    font-size: 0.82rem;
    line-height: 1.45;
}

.tests-v3-selection__module-groups {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 7px;
}

.tests-v3-selection__module-group {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding-left: 8px;
    border-left: 3px solid #94a3b8;
}

.tests-v3-selection__module-group--finished {
    border-left-color: rgb(var(--v-theme-success));
}

.tests-v3-selection__module-group--negative {
    border-left-color: rgb(var(--v-theme-error));
}

.tests-v3-selection__module-group--previous {
    border-left-color: rgb(var(--v-theme-warning));
}

.tests-v3-selection__module-group--current {
    border-left-color: rgb(var(--v-theme-primary));
}

.tests-v3-selection__module-group--additional {
    border-left-color: rgb(var(--v-theme-info));
}

.tests-v3-selection__module-group-header {
    display: flex;
    gap: 6px;
    align-items: center;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 800;
}

.tests-v3-selection__module-match {
    margin-left: auto;
    font-weight: 900;
}

.tests-v3-selection__module-codes {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 3px;
}

.tests-v3-selection__module-comparison {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 112px;
}

.tests-v3-selection__module-comparison-section {
    display: grid;
    gap: 2px;
}

.tests-v3-selection__module-comparison-label {
    color: #64748b;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.tests-v3-selection__module-comparison-divider {
    margin-block: 8px;
}

.tests-v3-selection__module-comparison-result {
    display: flex;
    justify-content: flex-end;
    margin-top: auto;
    padding-top: 8px;
}

.tests-v3-selection__module-comparison-result .tests-v3-selection__module-match {
    margin-left: 0;
}

.tests-v3-selection__module-code {
    padding: 1px 6px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #fff;
    color: #334155;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.4;
}

.tests-v3-test-summary__content {
    max-height: 70vh;
}

@media (max-width: 600px) {
    .tests-v3-study-plan__semester {
        grid-template-columns: 1fr;
        gap: 7px;
    }

    .tests-v3-study-plan__semester-label {
        padding-top: 0;
    }

    .tests-v3-students__actions {
        width: 100%;
        justify-content: flex-start;
    }

    .tests-v3-selection__module-groups {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 601px) and (max-width: 1100px) {
    .tests-v3-selection__module-groups {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.tests-v3-subnav__button {
    background: #dbeafe !important;
    border: 1px solid rgba(37, 99, 235, 0.22) !important;
    color: #1e3a8a !important;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
}

.tests-v3-subnav__button.v-btn--variant-flat {
    background: rgb(var(--v-theme-primary)) !important;
    border-color: rgba(30, 64, 175, 0.52) !important;
    color: rgb(var(--v-theme-on-primary)) !important;
}
</style>
