import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import RobotTimetable from '@/pages/admin/studentsTimetables/robot/RobotTimetable.vue'

describe('Students timetable robot page', () => {
    it('provides selectable robot timetable criteria', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/robot/RobotTimetable.vue',
            'utf8',
        )

        expect(componentSource).toContain('Stundenplan Wizzard')
        expect(componentSource).toContain('embeddedCourseCardsOnly')
        expect(componentSource).toContain('studentCode')
        expect(componentSource).toContain('syncExternalStudentSelection()')
        expect(componentSource).toContain('class="robot-timetable-embedded-course-cards"')
        expect(componentSource).toContain('name="course-actions"')
        expect(componentSource).toContain(':loading="courseCardsLoading"')
        expect(componentSource).toContain(':ready="courseCardsReady"')
        expect(componentSource).toContain(':extending="additionalCoursePanelVisible"')
        expect(componentSource).toContain(':has-selected-additional-courses="selectedAdditionalCourses.length > 0"')
        expect(componentSource).toContain(':extension-action-visible="additionalCourseExtensionActionVisible"')
        expect(componentSource).toContain("'robot-timetable-embedded-course-cards--with-additional': additionalCoursePanelVisible")
        expect(componentSource).toContain('class="robot-generator robot-generator--embedded"')
        expect(componentSource).toContain('fullGreenTimetableCountLoading || fullGreenTimetableCountError || timetableCountResultsAvailable || selectedRobotTimetable')
        expect(componentSource).toContain('.robot-generator--embedded')
        expect(componentSource).toContain('.robot-generator--embedded .robot-quality-card__items')
        expect(componentSource).toContain('grid-template-columns: repeat(3, minmax(0, 1fr));')
        expect(componentSource).toContain('<v-col v-else cols="12" lg="8" xl="7">')
        expect(componentSource).toContain('embedded-course-column')
        expect(componentSource).toContain('embedded-additional-course-column')
        expect(componentSource).toContain('.robot-timetable-embedded-course-cards .robot-course-panel')
        expect(componentSource).toContain('box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);')
        expect(componentSource).toContain('background: rgba(219, 234, 254, 0.72);')
        expect(componentSource).toContain('background: rgba(255, 237, 213, 0.86);')
        expect(componentSource).toContain('min-height: 39px;')
        expect(componentSource).toContain('@media (min-width: 1280px)')
        expect(componentSource).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(componentSource).toContain('.robot-timetable-embedded-course-cards--with-additional')
        expect(componentSource).toContain('.robot-timetable-embedded-course-cards--with-additional .robot-course-columns')
        expect(componentSource).toContain('icon="mdi-information-outline"')
        expect(componentSource).toContain('infoDialogOpen')
        expect(componentSource).toContain('<v-dialog v-model="infoDialogOpen" persistent max-width="680">')
        expect(componentSource).toContain('Hinweise zum Stundenplan Wizzard')
        expect(componentSource).toContain('Die Note B bedeutet befreit')
        expect(componentSource).toContain('Grundregel für Folgemodule')
        expect(componentSource).toContain('M5 darf erst gebucht werden, wenn M3 positiv')
        expect(componentSource).toContain('D7 und D8 gemeinsam gebucht')
        expect(componentSource).toContain('semesterOptions()')
        expect(componentSource).toContain('Array.from({ length: 8 }')
        expect(componentSource).toContain('label="Semester"')
        expect(componentSource).toContain('label="Ethik / Religion"')
        expect(componentSource).toContain('label="Zweig"')
        expect(componentSource).toContain('label="ME / BE"')
        expect(componentSource).toContain('label="Sprache"')
        expect(componentSource).toContain('Tage, an denen ich kann')
        expect(componentSource).toContain('Diese Stunden verwenden')
        expect(componentSource).toContain('Einzelne Zeiten sperren')
        expect(componentSource).toContain("ETH - Ethik")
        expect(componentSource).toContain("Rev - Religion evangelisch")
        expect(componentSource).toContain("Ris - Religion Islam")
        expect(componentSource).toContain("Rk - Religion katholisch")
        expect(componentSource).toContain("Ror - Religion orthodox")
        expect(componentSource).toContain("Wirtschaftskundlicher Zweig")
        expect(componentSource).toContain("Gymnasialer Zweig")
        expect(componentSource).toContain("ME - Musikerziehung")
        expect(componentSource).toContain("BE - Bildnerische Erziehung")
        expect(componentSource).toContain("L - Latein")
        expect(componentSource).toContain("F - Französisch")
        expect(componentSource).toContain("S - Spanisch")
        expect(componentSource).toContain('weekdayOptions()')
        expect(componentSource).toContain('timeOptions()')
        expect(componentSource).toContain('weekdayTimeOptions()')
        expect(componentSource).toContain('selectedConstraintSummary()')
        expect(componentSource).toContain("students-timetables:robot:last-settings")
        expect(componentSource).toContain("students-timetables:overview:last-timetable")
        expect(componentSource).toContain("const OVERVIEW_TIMETABLE_PATH = '/admin/students-timetables/timetable/overview'")
        expect(componentSource).toContain('In Übersicht übernehmen')
        expect(componentSource).toContain('overtakeSelectedTimetableToOverview')
        expect(componentSource).toContain('selectedRobotTimetableCourseGroupKeys()')
        expect(componentSource).toContain('overviewTimetableStateForSelectedRobotTimetable()')
        expect(componentSource).toContain('selection: this.overviewSelectionState()')
        expect(componentSource).toContain('overviewSelectionState()')
        expect(componentSource).toContain('transferredStudentContext: this.overviewStudentContext()')
        expect(componentSource).toContain('overviewStudentContext()')
        expect(componentSource).toContain('restoreLastRobotState()')
        expect(componentSource).toContain('saveLastRobotState()')
        expect(componentSource).toContain('ensureRobotConfigLoaded()')
        expect(componentSource).toContain('currentRobotState()')
        expect(componentSource).toContain('robotStorageKey()')
        expect(componentSource).toContain('robotSchoolyearId()')
        expect(componentSource).toContain('robotStorageKeys()')
        expect(componentSource).toContain("...mapWritableState(useAdminStore, ['config', 'selected_schoolyear'])")
        expect(componentSource).toContain('selectedCourseKeysForState()')
        expect(componentSource).toContain('selectedCourseCodesForState()')
        expect(componentSource).toContain('restoredDeselectedCourseKeys(state)')
        expect(componentSource).toContain('selectedCourseKeys')
        expect(componentSource).toContain('selectedCourseCodes')
        expect(componentSource).toContain('if (this.loading || this.robotStateRestoring) return')
        expect(componentSource).toContain('availableWeekdays: [1, 2, 3, 4, 5, 6]')
        expect(componentSource).toContain('unavailableWeekdays()')
        expect(componentSource).toContain("selectedOptionTitles(options, values, separator = '\\n')")
        expect(componentSource).toContain("selectedTimeOptionTitles(options, values, separator = '\\n')")
        expect(componentSource).toContain('compactTimeOptionRanges(options, values)')
        expect(componentSource).toContain('timeOptionRangeTitle(range)')
        expect(componentSource).toContain('.join(separator)')
        expect(componentSource).toContain("this.selectedOptionTitles(this.weekdayOptions, this.unavailableWeekdays, ', ')")
        expect(componentSource).toContain('white-space: pre-line')
        expect(componentSource).toContain('constraintSelected(key, value)')
        expect(componentSource).toContain('toggleConstraint(key, value)')
        expect(componentSource).toContain('constraintsDraft')
        expect(componentSource).toContain('openConstraintsDialog()')
        expect(componentSource).toContain('updateConstraints()')
        expect(componentSource).toContain('weekdayTimeValue(weekday, time)')
        expect(componentSource).toContain('weekdayTimeAvailable(weekday, time)')
        expect(componentSource).toContain('toggleWeekdayTime(weekday, time)')
        expect(componentSource).toContain("draftWeekdayTimeAvailable(weekday.value, time.value) ? 'success' : 'error'")
        expect(componentSource).toContain('robot-chip-row')
        expect(componentSource).toContain('robot-time-matrix')
        expect(componentSource).toContain('@click="toggleDraftWeekdayTime')
        expect(componentSource).toContain('Montag')
        expect(componentSource).toContain('Samstag')
        expect(componentSource).toContain('1}. Stunde')
        expect(componentSource).not.toContain('<div class="robot-constraints__title">Zeitvorgaben</div>')
        expect(componentSource).toContain('Zeitliche Einschränkungen')
        expect(componentSource).toContain('<div class="robot-selected-card__label">Student</div>')
        expect(componentSource).toContain('Kein Student')
        expect(componentSource).toContain('studentDialogOpen')
        expect(componentSource).toContain('<v-dialog v-model="studentDialogOpen" persistent max-width="560">')
        expect(componentSource).toContain('<v-text-field')
        expect(componentSource).toContain('ref="studentSearchField"')
        expect(componentSource).toContain('v-model="studentSearch"')
        expect(componentSource).toContain('@keydown.enter.prevent="submitStudentSearch"')
        expect(componentSource).toContain('clearable')
        expect(componentSource).toContain('filteredStudentResults()')
        expect(componentSource).toContain('selectStudentDraft(studentCode)')
        expect(componentSource).toContain('submitStudentSearch()')
        expect(componentSource).toContain('focusStudentSearchField()')
        expect(componentSource).toContain('studentSearchReady()')
        expect(componentSource).toContain('this.normalizedStudentSearch.length >= 2')
        expect(componentSource).toContain('label="Student suchen"')
        expect(componentSource).toContain('studentTotalCountLabel()')
        expect(componentSource).toContain('Studenten gesamt')
        expect(componentSource).toContain('studentSemesterBySchoolLevel()')
        expect(componentSource).toContain("'09_1': 1")
        expect(componentSource).toContain("'12_2': 8")
        expect(componentSource).toContain('studentSemesterLabel(student)')
        expect(componentSource).toContain('class="robot-student-selection__actions"')
        expect(componentSource).toContain('title="Student bearbeiten"')
        expect(componentSource).toContain('title="Student löschen"')
        expect(componentSource).toContain('title="Student-Einstellungen zurücksetzen"')
        expect(componentSource).toContain(':disabled="!studentCourseSelectionResettable"')
        expect(componentSource).toContain('@click="resetStudentCourseSelection"')
        expect(componentSource).toContain('icon="mdi-close-circle-outline"')
        expect(componentSource).toContain('@click="clearStudentSelection"')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/robot/students')")
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/robot/student-completed-courses'")
        expect(componentSource).toContain('Abgeschlossene Kurse')
        expect(componentSource).toContain('Fehlende Kurse')
        expect(componentSource).toContain('Vorgesehene Kurse')
        expect(componentSource).toContain('Zusätzliche Kurse')
        expect(componentSource).toContain('robot-student-course-section--completed')
        expect(componentSource).toContain('robot-student-course-section--missing')
        expect(componentSource).toContain('robot-student-course-section--planned')
        expect(componentSource).toContain('robot-student-course-section--additional')
        expect(componentSource).toContain('.robot-student-course-section--missing')
        expect(componentSource).toContain('rgba(var(--v-theme-error), 0.1)')
        expect(componentSource).toContain('.robot-student-course-section--planned')
        expect(componentSource).toContain('.robot-student-course-section--additional')
        expect(componentSource).toContain('background: rgba(var(--v-theme-success), 0.18)')
        expect(componentSource).toContain('border-color: rgba(var(--v-theme-success), 0.42)')
        expect(componentSource).toContain('background: #bbf7d0')
        expect(componentSource).toContain('border-color: #15803d')
        expect(componentSource).toContain('v-if="additionalCoursePanelVisible"')
        expect(componentSource).toContain('additionalCoursePanelVisible()')
        expect(componentSource).toContain('additionalCourseColumns')
        expect(componentSource).toContain('robot-course-item-panels')
        expect(componentSource).not.toContain('robot-additional-course-panel-row')
        expect(componentSource).not.toContain('robot-additional-course-row')
        expect(componentSource).toContain('grid-template-columns: repeat(2, minmax(0, 1fr))')
        expect(componentSource).toContain(':model-value="additionalCourseFullySelected(course)"')
        expect(componentSource).toContain(':indeterminate="additionalCoursePartiallySelected(course)"')
        expect(componentSource).toContain(':model-value="additionalCourseGroupSelected(course, group)"')
        expect(componentSource).toContain('setAdditionalCourseGroupSelected(course, group, $event)')
        expect(componentSource).toContain(':disabled="!courseSelectable(course)"')
        expect(componentSource).not.toContain(':disabled="additionalCourseInteractionDisabled(course)"')
        expect(componentSource).toContain(':disabled="!additionalCourseSelectable(course) || additionalCourseInteractionDisabled(course)"')
        expect(componentSource).toContain("'robot-course-item-panel--no-timetable-hours': !courseSelectable(course)")
        expect(componentSource).toContain("'robot-course-item-panel--missing-additional': additionalCourseInteractionDisabled(course)")
        expect(componentSource).toContain("'robot-student-completed-course--no-timetable-hours': !courseSelectable(course)")
        expect(componentSource).not.toContain('<sup v-if="courseGroupWeekMarker(group)"')
        expect(componentSource).toContain('setAdditionalCourseSelected(course, $event)')
        expect(componentSource).toContain('courseHasTimetableHours(course)')
        expect(componentSource).toContain('additionalCoursePrerequisiteCourse(course)')
        expect(componentSource).toContain('courseUsesTwoLevelAdditionalPrerequisite(base)')
        expect(componentSource).toContain('grid-template-columns: repeat(auto-fill, minmax(120px, 1fr))')
        expect(componentSource).toContain('studentMissingCourses')
        expect(componentSource).toContain('studentPlannedCourses')
        expect(componentSource).toContain('regularCourseListTitle()')
        expect(componentSource).toContain('regularCourseColumns()')
        expect(componentSource).toContain('class="robot-regular-course-column-title"')
        expect(componentSource).toContain('v-for="course in courseColumn.courses"')
        expect(componentSource).toContain("Fehlende Kurse + Vorgesehene Kurse")
        expect(componentSource).toContain('applyStudentPlannedCourseSelection()')
        expect(componentSource).toContain('courseMatchesStudentPlannedCourse(course, plannedCourseCodes)')
        expect(componentSource).toContain('selectAllAvailableCourses()')
        expect(componentSource).toContain('studentAdditionalCourses')
        expect(componentSource).toContain('completedCourseCountsAsDone(grade)')
        expect(componentSource).toContain('courseCompletedForStudentPlanning(course, completedCourseCodes)')
        expect(componentSource).toContain('courseMatchesCourseCodeSet(course, courseCodes)')
        expect(componentSource).toContain('regularCourseCodes')
        expect(componentSource).toContain('studentPlannedCourseCodes(plannedCourses)')
        expect(componentSource).toContain('coursesForSemester(semester)')
        expect(componentSource).toContain('coursePossibleAsStudentAdditional(course, completedCourseCodes, visitedCourseCodes, plannedCourseCodes')
        expect(componentSource).toContain('courseModulePrerequisiteMet(parts, completedCourseCodes, visitedCourseCodes, {')
        expect(componentSource).toContain('studentCompletedCourses')
        expect(componentSource).toContain('studentCompletedCoursesExpanded')
        expect(componentSource).toContain('toggleStudentCompletedCourses')
        expect(componentSource).toContain('@click="toggleStudentCompletedCourses"')
        expect(componentSource).toContain('class="robot-selected-card robot-selected-card--button"')
        expect(componentSource).toContain('class="robot-student-selection__content"')
        expect(componentSource).toContain('margin-bottom: 12px')
        expect(componentSource).toContain('Keine abgeschlossenen Kurse mit Note gefunden.')
        expect(componentSource.indexOf('class="robot-student-selection"')).toBeLessThan(
            componentSource.indexOf('class="robot-selection"'),
        )
        expect(componentSource).toContain('student: this.studentSelection')
        expect(componentSource).toContain('Keine Zeiten')
        expect(componentSource).toContain('robot-selected-cards')
        expect(componentSource).toContain('robot-selected-card')
        expect(componentSource).toContain('icon="mdi-pencil"')
        expect(componentSource).toContain('<v-dialog v-model="selectionDialogOpen" persistent max-width="640">')
        expect(componentSource).toContain('v-model="selectionDraft.semester"')
        expect(componentSource).toContain('v-model="selectionDraft.religion"')
        expect(componentSource).toContain('v-model="selectionDraft.branch"')
        expect(componentSource).toContain('v-model="selectionDraft.artsSubject"')
        expect(componentSource).toContain('v-model="selectionDraft.language"')
        expect(componentSource).toContain('openSelectionDialog()')
        expect(componentSource).toContain('updateSelection()')
        expect(componentSource).toContain('Aktualisieren')
        expect(componentSource).toContain('<v-dialog v-model="constraintsDialogOpen" persistent max-width="760">')
        expect(componentSource).toContain('Zeitvorgaben bearbeiten')
        expect(componentSource).toContain('title="Einschränkungen bearbeiten"')
        expect(componentSource).toContain('robot-selected-cards--constraints')
        expect(componentSource).toContain('availableTimes: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]')
        expect(componentSource).toContain('unavailableTimes()')
        expect(componentSource).toContain('Nicht verwendete Stunden')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/school-hours')")
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/evaluation-settings')")
        expect(componentSource).toContain('defaultTimeOptions()')
        expect(componentSource).toContain('timeOptionTitle(schoolHour)')
        expect(componentSource).toContain('timeOptionShortTitle(schoolHour)')
        expect(componentSource).toContain('timeRangeLabel(schoolHour)')
        expect(componentSource).toContain('formatTimeValue(value)')
        expect(componentSource).toContain('syncAvailableTimes()')
        expect(componentSource).toContain('selectedSummary()')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/subjects-overview-settings')")
        expect(componentSource).toContain('subjectMappings')
        expect(componentSource).toContain('deselectedCourseKeys')
        expect(componentSource).toContain('deselectedCourseGroupKeys')
        expect(componentSource).toContain('availableCourses()')
        expect(componentSource).toContain('availableCourseColumns()')
        expect(componentSource).toContain('courseCardsReady()')
        expect(componentSource).toContain('courseCardsLoading()')
        expect(componentSource).toContain('selectedCourses()')
        expect(componentSource).toContain('selectedCoursesHours()')
        expect(componentSource).toContain('courseSelected(course)')
        expect(componentSource).toContain('courseFullySelected(course)')
        expect(componentSource).toContain('coursePartiallySelected(course)')
        expect(componentSource).toContain('setCourseSelected(course, selected)')
        expect(componentSource).toContain(':disabled="!courseSelectionResettable"')
        expect(componentSource).toContain('@click="resetCourseSelection"')
        expect(componentSource).toContain('studentSettingsResettable()')
        expect(componentSource).toContain('studentCourseSelectionResettable()')
        expect(componentSource).toContain('selectionStateSnapshot(selection)')
        expect(componentSource).toContain('resetStudentCourseSelection()')
        expect(componentSource).toContain('courseOverlapsInSelectedTimetable(course)')
        expect(componentSource).toContain('courseUsedInSelectedTimetable(course)')
        expect(componentSource).toContain('selectedTimetableScheduledCourses()')
        expect(componentSource).toContain('robot-course-item-panel--used')
        expect(componentSource).toContain('selectedTimetableConflictCourses()')
        expect(componentSource).toContain('robot-course-item-panel--conflict')
        expect(componentSource).toContain('courseGroupUsedInSelectedTimetable(course, group)')
        expect(componentSource).toContain('courseGroupNoLongerFitsSelectedTimetable(course, group)')
        expect(componentSource).toContain('robot-course-item-detail--used')
        expect(componentSource).toContain('robot-course-item-detail--conflict')
        expect(componentSource).toContain('courseGroupSelectionKeys(course)')
        expect(componentSource).not.toContain('selectAllCourses()')
        expect(componentSource).not.toContain('deselectAllCourses()')
        expect(componentSource).not.toContain('Alle auswählen')
        expect(componentSource).not.toContain('Alle abwählen')
        expect(componentSource).toContain('robot-course-panel__body')
        expect(componentSource).toContain('robot-course-item-header--expandable')
        expect(componentSource).toContain('.robot-course-item-header > :first-child')
        expect(componentSource).toContain('justify-content: center')
        expect(componentSource).not.toContain('courseSelectionPanels')
        expect(componentSource).toContain('v-model="courseItemPanels"')
        expect(componentSource).toContain('@update:model-value="setCourseSelected(course, $event)"')
        expect(componentSource).toContain('Stundenpläne erstellen')
        expect(componentSource).toContain('Volle grüne Stundenpläne')
        expect(componentSource).toContain('Grüne Stundenpläne')
        expect(componentSource).toContain('qualityCounters')
        expect(componentSource).toContain('quality_counters')
        expect(componentSource).toContain('selected_additional_course_keys: this.selectedAdditionalCourses.map(course => course.key)')
        expect(componentSource).toContain('additional_course_timetable_count')
        expect(componentSource).toContain('selectedAdditionalCourses')
        expect(componentSource).toContain('additionalCourseTimetableCountLabel')
        expect(componentSource).toContain('additionalCourseTimetableCountCardValue')
        expect(componentSource).toContain('additionalCourseTimetableCountCardVisible')
        expect(componentSource).toContain('additionalCourseTimetableCountCardTitle')
        expect(componentSource).toContain('inkl. Zusätzliche Stunden')
        expect(componentSource).toContain('autoSelectAdditionalCourseTimetableFilter(options)')
        expect(componentSource).toContain('additionalCourseTimetableRequired')
        expect(componentSource).toContain('selected_additional_courses_required: this.additionalCourseTimetableRequired === true')
        expect(componentSource).toContain('@click="setAdditionalCourseTimetableRequired(true)"')
        expect(componentSource).not.toContain('setAdditionalCourseTimetableRequired($event)')
        expect(componentSource).toContain(':disabled="!additionalCourseSelectionResettable"')
        expect(componentSource).toContain('@click="resetAdditionalCourseSelection"')
        expect(componentSource).toContain('additionalCoursesAcceptedBySelectedTimetable()')
        expect(componentSource).toContain('selectedTimetableMissingAdditionalCourses()')
        expect(componentSource).toContain('selectedTimetableMissingAdditionalCourseLabels()')
        expect(componentSource).toContain('additionalCourseMissingInSelectedTimetable(course)')
        expect(componentSource).toContain('courseAllGroupsNoLongerFitSelectedTimetable(course)')
        expect(componentSource).toContain('robot-course-item-panel--missing-additional')
        expect(componentSource).toContain('selectedTimetableScheduledCourseKeySet()')
        expect(componentSource).toContain('selectedTimetableConflictCourseKeySet()')
        expect(componentSource).toContain('selectedTimetableMissingAdditionalCourseKeySet()')
        expect(componentSource).toContain('additionalCourseInteractionDisabledKeySet()')
        expect(componentSource).toContain('courseMatchesComparisonKeySet(course, comparisonKeySet)')
        expect(componentSource).toContain('type="error"')
        expect(componentSource).toContain('additionalCourseAcceptanceLabel()')
        expect(componentSource).toContain('Nicht alle gewählten Zusatzkurse konnten berücksichtigt werden')
        expect(componentSource).not.toContain('additionalCourseTimetableCountDetail()')
        expect(componentSource).toContain('robot-count-card--additional')
        expect(componentSource.indexOf('robot-count-card--additional')).toBeLessThan(
            componentSource.indexOf('robot-count-card--conflict'),
        )
        expect(componentSource).toContain('robot-generated-cell--additional')
        expect(componentSource).toContain('robot-course-fu')
        expect(componentSource).toContain('robot-course-week-marker')
        expect(componentSource).toContain('courseWeekMarker(course)')
        expect(componentSource).toContain('generatedSlotWeekMarker(robotTimetableDisplaySlot(weekday.value, time.value), selectedRobotTimetable)')
        expect(componentSource).not.toContain('v-if="courseDistanceLearning(course)"')
        expect(componentSource).not.toContain('v-if="courseWeekMarker(course)"')
        expect(componentSource).toContain('isDistanceLearningCourse')
        expect(componentSource).toContain('isAdditionalCourse')
        expect(componentSource).toContain('allQualityCriteriaCount')
        expect(componentSource).toContain('all_quality_criteria_count')
        expect(componentSource).toContain('evaluationCriteria')
        expect(componentSource).toContain('evaluation_criteria: this.storageEvaluationCriteria(this.evaluationCriteria)')
        expect(componentSource).toContain('enabledEvaluationCriteriaFromSettings')
        expect(componentSource).toContain('qualityCriterionRows()')
        expect(componentSource).toContain('activeQualityCriterionRows()')
        expect(componentSource).toContain('evaluationCriteriaSettings')
        expect(componentSource).toContain('applyEvaluationCriteriaSettings(criteria)')
        expect(componentSource).toContain('Qualitätskriterien')
        expect(componentSource).toContain('Alle Qualitätskriterien erfüllt')
        expect(componentSource).toContain('v-for="(counter, counterIndex) in qualityCriterionRows"')
        expect(componentSource).toContain('{{ counterIndex + 1 }}. {{ counter.label }}')
        expect(componentSource).toContain('robot-quality-card__item--summary')
        expect(componentSource).toContain('grid-template-columns: repeat(2, minmax(0, 1fr))')
        expect(componentSource).toContain('grid-column: 1 / -1')
        expect(componentSource).toContain('allQualityCriteriaCountLabel()')
        expect(componentSource).toContain('allQualityCriteriaCountDetail()')
        expect(componentSource).toContain('qualityCounterCountLabel(counter)')
        expect(componentSource).toContain('qualityCounterFulfilledCountLabel(counter)')
        expect(componentSource).toContain('qualityCounterDetail(counter)')
        expect(componentSource).toContain('resetQualityCounterSelection()')
        expect(componentSource).toContain('setEvaluationCriterionEnabled(counter, $event)')
        expect(componentSource).toContain('<v-checkbox-btn')
        expect(componentSource).toContain('qualityCounterReached(counter)')
        expect(componentSource).toContain('robot-quality-summary__check')
        expect(componentSource).toContain(':model-value="false"')
        expect(componentSource).toContain('readonly')
        expect(componentSource).toContain('{{ counter.label }}: {{ qualityCounterFulfilledCountLabel(counter) }}')
        expect(componentSource).toContain("selectedTimetableResultType: 'full_green'")
        expect(componentSource).toContain("timetableResultCardSelected('full_green')")
        expect(componentSource).toContain("timetableResultCardSelected('green')")
        expect(componentSource).toContain("timetableResultCardSelected('conflict')")
        expect(componentSource).toContain("setSelectedTimetableResultType('full_green', $event)")
        expect(componentSource).toContain("setSelectedTimetableResultType('green', $event)")
        expect(componentSource).toContain("setSelectedTimetableResultType('conflict', $event)")
        expect(componentSource).toContain('setSelectedTimetableResultType(type, selected)')
        expect(componentSource).toContain('timetableResultCardSelected(type)')
        expect(componentSource).toContain('isTimetableResultTypeSelectable(type)')
        expect(componentSource).toContain("backendCountCardSelectable('full_green')")
        expect(componentSource).toContain("backendCountCardSelectable('green')")
        expect(componentSource).toContain("backendCountCardSelectable('conflict')")
        expect(componentSource).toContain("backendCountCardSelectable('green') || timetableResultCardSelected('green')")
        expect(componentSource).toContain('isAdditionalCourseTimetableFilterSelectable() || additionalCourseTimetableRequired')
        expect(componentSource).toContain('Grüne Stundenpläne auswählbar')
        expect(componentSource).toContain('Stundenpläne mit Zusatzkursen auswählbar')
        expect(componentSource).toContain("selectBackendCountCard('full_green')")
        expect(componentSource).toContain("selectBackendCountCard('green')")
        expect(componentSource).toContain("selectBackendCountCard('conflict')")
        expect(componentSource).toContain('robot-count-card--clickable')
        expect(componentSource).toContain(`:disabled="!isTimetableResultTypeSelectable('full_green')"`)
        expect(componentSource).toContain(`:disabled="!isTimetableResultTypeSelectable('green')"`)
        expect(componentSource).toContain('robot-count-card--selected')
        expect(componentSource).toContain('0 0 0 3px rgba(var(--v-theme-success), 0.2)')
        expect(componentSource).toContain('0 0 0 3px rgba(234, 88, 12, 0.22)')
        expect(componentSource).toContain('robot-count-cards')
        expect(componentSource).toContain('grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))')
        expect(componentSource).toContain('fullGreenTimetableNumber')
        expect(componentSource).toContain('greenTimetableNumber')
        expect(componentSource).toContain('conflictTimetableNumber')
        expect(componentSource).toContain('hasGreenTimetableResults()')
        expect(componentSource).toContain('showConflictTimetableResults()')
        expect(componentSource).toContain('v-if="backendVariationCountsAvailable || showConflictTimetableResults"')
        expect(componentSource).toContain('selectedTimetableResultCount()')
        expect(componentSource).toContain('qualityCriteriaResultFilterActive()')
        expect(componentSource).toContain('timetableResultCounterLimitForCounter(type)')
        expect(componentSource).toContain('timetableCountResultsAvailable()')
        expect(componentSource).toContain('selectedOptionsNoResultAlertVisible()')
        expect(componentSource).toContain('selectedOptionsNoResultReasons()')
        expect(componentSource).toContain('noBaseTimetableResultReasons()')
        expect(componentSource).toContain('Keine passenden Stundenpläne für die aktuelle Auswahl.')
        expect(componentSource).toContain('Der Zusatzkurs-Filter ist aktiv')
        expect(componentSource).toContain('Kein ${this.selectedTimetableResultPluralTitle()} erfüllt alle aktiven Bewertungskriterien')
        expect(componentSource).toContain('timetableResultCounterLimit(type)')
        expect(componentSource).toContain('selectedTimetableResultTitle()')
        expect(componentSource).toContain('robot-timetable-selector')
        expect(componentSource).toContain('v-if="selectedTimetableResultCount > 0"')
        expect(componentSource).toContain('aria-live="polite"')
        expect(componentSource).toContain('grid-template-columns: 34px minmax(104px, auto) 34px')
        expect(componentSource).toContain('icon="mdi-chevron-left"')
        expect(componentSource).toContain('icon="mdi-chevron-right"')
        expect(componentSource).toContain('moveTimetableResultCounter(selectedTimetableResultType, -1)')
        expect(componentSource).toContain('moveTimetableResultCounter(selectedTimetableResultType, 1)')
        expect(componentSource).toContain('normalizeTimetableResultCounters()')
        expect(componentSource).toContain('Stundenpläne mit Konflikten')
        expect(componentSource).toContain('Rote Stundenpläne')
        expect(componentSource).toContain('conflict_timetable_count')
        expect(componentSource).toContain('selected_timetable_type: this.selectedTimetableResultType')
        expect(componentSource).toContain('selected_timetable_number: this.timetableResultCounter(this.selectedTimetableResultType)')
        expect(componentSource).toContain('include_quality_counters: options?.calculateQualityCounters === true')
        expect(componentSource).toContain("'/api/admin/students-timetables/robot/quality-counters'")
        expect(componentSource).toContain('this.generatedTimetables = []')
        expect(componentSource).toContain('backendTimetableFromResponse(response.data.data.selected_timetable)')
        expect(componentSource).toContain('shouldLoadConflictTimetableForRequiredAdditionalCourses(selectedTimetable)')
        expect(componentSource).toContain('timetableIncludesSelectedAdditionalCourses(timetable)')
        expect(componentSource).toContain('timetableScheduledCourseComparisonKeys(timetable)')
        expect(componentSource).toContain('selectedRobotTimetable()')
        expect(componentSource).toContain('robotTimetableSlot(weekday, time)')
        expect(componentSource).toContain('robotTimetableDisplaySlot(weekday, time)')
        expect(componentSource).toContain('robotTimetableCellClasses(weekday, time)')
        expect(componentSource).toContain('slotHasVisualConflict(slot)')
        expect(componentSource).toContain('robotTimetableOccasionalMarkers(weekday, time)')
        expect(componentSource).toContain('selectedOccasionalAppointmentGroupsForTimetable(timetable)')
        expect(componentSource).toContain('@click="createTimetables"')
        expect(componentSource).toContain(':loading="timetableGenerationLoading"')
        expect(componentSource).toContain(':disabled="loading || timetableGenerationLoading || !selectedCourses.length"')
        expect(componentSource).toContain(':disabled="timetableGenerationLoading || !selectedRobotTimetableCourseGroupKeys().length"')
        expect(componentSource).toContain(':disabled="timetableGenerationLoading || timetableResultCounter(selectedTimetableResultType) <= 1"')
        expect(componentSource).toContain(':disabled="timetableGenerationLoading || timetableResultCounter(selectedTimetableResultType) >= selectedTimetableResultCount"')
        expect(componentSource).toContain('async createTimetables(options = {})')
        expect(componentSource).toContain('prepareTimetableCreationOptions(options)')
        expect(componentSource).toContain('prepareTimetableCreationOptions(options = {})')
        expect(componentSource).toContain('clearAdditionalCourseSelectionForTimetableCreation()')
        expect(componentSource).toContain('this.timetableCreateLoading = true')
        expect(componentSource).toContain('await this.loadFullGreenTimetableCount({ preferFullGreen: true })')
        expect(componentSource).toContain('options?.preferFullGreen === true')
        expect(componentSource).toContain("'/api/admin/students-timetables/robot/backend-timetable'")
        expect(componentSource).toContain('selected_course_keys: this.selectedCourses.map(course => course.key)')
        expect(componentSource).toContain('timetable_variation_count')
        expect(componentSource).toContain('Variationen gesamt')
        expect(componentSource).toContain('totalTimetableVariationCountLabel')
        expect(componentSource).toContain('backendVariationCountsAvailable')
        expect(componentSource).toContain('selectedRobotTimetable.statusMessage')
        expect(componentSource).toContain('emptyTimetableWeekdays()')
        expect(componentSource).toContain('emptyTimetableTimes()')
        expect(componentSource).toContain('fullGreenTimetableCountLabel()')
        expect(componentSource).toContain('formatNumber(value)')
        expect(componentSource).toContain('robot-generator__actions')
        expect(componentSource).toContain('generateTimetables()')
        expect(componentSource).toContain('generatedTimetables')
        expect(componentSource).toContain('generationProblems')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/course-groups')")
        expect(componentSource).toContain('configuredCourseGroups()')
        expect(componentSource).toContain('timetableCandidateSets(courses)')
        expect(componentSource).toContain('timetableOptionsForCourse(course, courseGroups = null, completeOptions = true)')
        expect(componentSource).toContain('courseGroupMatchesCourse(courseGroup, course)')
        expect(componentSource).toContain('isOccasionalCourseGroup(courseGroup)')
        expect(componentSource).toContain('defaultTimetableCodeAlias(value)')
        expect(componentSource).toContain('buildTimetableCombinations(')
        expect(componentSource).toContain('mergeTimetableOptionsBySlots(options)')
        expect(componentSource).toContain('keine passenden TT-Stunden gefunden')
        expect(componentSource).toContain('generatedWeekdays()')
        expect(componentSource).toContain('generatedTimes()')
        expect(componentSource).toContain('clearGeneratedTimetables()')
        expect(componentSource).toContain('<template v-if="timetableCountResultsAvailable">')
        expect(componentSource).toContain('<div v-if="selectedRobotTimetable" class="robot-generated">')
        expect(componentSource).toContain('<div class="robot-course-list__header robot-generated-header">')
        expect(componentSource).toContain('<div class="robot-course-list__title">Stundenplan</div>')
        expect(componentSource).toContain('robot-generated-grid')
        expect(componentSource).toContain('robot-generated-cell__details')
        expect(componentSource).toContain('generatedSlotDetails(slot)')
        expect(componentSource).toContain('robot-generated-cell--conflict')
        expect(componentSource).toContain('robot-generated-cell__conflicts')
        expect(componentSource).toContain('generatedSlotConflicts(slot)')
        expect(componentSource).toContain('generatedSlotConflictBlocks(slot)')
        expect(componentSource).toContain('robot-generated-cell__same-slots')
        expect(componentSource).toContain('generatedSlotSameSlotBlocks(slot)')
        expect(componentSource).toContain('generatedCellOccasionalMarkers(timetable, weekday, time)')
        expect(componentSource).toContain('robot-generated-cell__occasional-marker')
        expect(componentSource).toContain('robot-generated-appointments')
        expect(componentSource).toContain('displayedTimetableProblems(timetable)')
        expect(componentSource).toContain('problemIsCoveredByOccasionalAppointments(problem)')
        expect(componentSource).toContain('groupedOccasionalAppointments(timetable)')
        expect(componentSource).toContain('displayedOccasionalAppointmentGroups(selectedRobotTimetable)')
        expect(componentSource).toContain('timetableSlotOccasionalAppointments(timetable)')
        expect(componentSource).toContain('v-for="row in group.rows"')
        expect(componentSource).toContain("'robot-generated-appointment--clear': !row.hasConflict")
        expect(componentSource).toContain("'robot-generated-appointment--conflict': row.hasConflict")
        expect(componentSource).toContain("row.hasConflict ? 'mdi-alert-circle-outline' : 'mdi-check-circle-outline'")
        expect(componentSource).toContain('occasionalAppointmentGroupTitle(appointment)')
        expect(componentSource).toContain('defaultOccasionalAppointmentGroupSelections(timetable)')
        expect(componentSource).toContain('lowestConflictOccasionalAppointmentGroup(groups)')
        expect(componentSource).toContain('occasionalAppointmentGroupConflictCount(group)')
        expect(componentSource).toContain('occasionalAppointmentGroupSelectable(timetable, group)')
        expect(componentSource).toContain('occasionalAppointmentGroupHasAlternatives(timetable, group)')
        expect(componentSource).toContain('occasionalAppointmentGroupMatchesScheduledCourse(timetable, group)')
        expect(componentSource).toContain('setOccasionalAppointmentGroupSelected(timetable, group, selected)')
        expect(componentSource).toContain('selectedOccasionalAppointmentGroups')
        expect(componentSource).toContain('robot-generated-appointment-group__check')
        expect(componentSource).toContain('occasionalAppointments')
        expect(componentSource).toContain('occasionalAppointmentItem(course, option, courseGroup, existingSlot = null)')
        expect(componentSource).toContain('timetableWithOccasionalSlots')
        expect(componentSource).toContain('visibleOccasionalOptionsForCombination(candidateSet, slots)')
        expect(componentSource).toContain('scheduledCourseOptionLabels(course, slots)')
        expect(componentSource).toContain('robot-problems')
        expect(componentSource).toContain('courseGroupDateTimeLabel(courseGroup)')
        expect(componentSource).toContain('courseGroupDateTimeLabels(courseGroup)')
        expect(componentSource).toContain('courseGroupDateTimeEntries(courseGroup)')
        expect(componentSource).toContain('formatDateWithWeekdayLabel(value)')
        expect(componentSource).toContain('weekdayLabelForDate(value)')
        expect(componentSource).toContain('trackOccasionalCourseConflict(conflicts, course, courseGroup, existingSlot)')
        expect(componentSource).toContain('optionHasBlockingUsedSlot(option, assignedSlots, usedSlotKeys)')
        expect(componentSource).toContain('courseGroupsBlockTimetableSlot(leftCourseGroup, rightCourseGroup)')
        expect(componentSource).toContain('candidateSetBlockedRegularGroupsLabel(candidateSet)')
        expect(componentSource).toContain('candidateSetBlockedRegularGroupItems(candidateSet)')
        expect(componentSource).toContain('problemSummary(problem)')
        expect(componentSource).toContain('problemDetails(problem)')
        expect(componentSource).toContain('robot-problems__details')
        expect(componentSource).toContain('candidateSetConflictProblemMessage')
        expect(componentSource).toContain('subjectMatchesSelectedBranch(subject)')
        expect(componentSource).toContain('subjectMatchesSelectedChoices(subject)')
        expect(componentSource).toContain('languageSubjectMatchesSelection(subject)')
        expect(componentSource).toContain('languageSubjectCode(subject)')
        expect(componentSource).toContain('selectedCourseFromSubject(subject)')
        expect(componentSource).toContain('selectedCourseCode(subject)')
        expect(componentSource).toContain('selectedCourseName(subject)')
        expect(componentSource).toContain('isReligionSubject(subject)')
        expect(componentSource).toContain('isLanguageSubject(subject)')
        expect(componentSource).toContain('isArtsSubject(subject)')
        expect(componentSource).toContain('robot-course-item-panels')
        expect(componentSource).toContain('courseGroupItems(course)')
        expect(componentSource).toContain('courseGroupSelected(course, group)')
        expect(componentSource).toContain('setCourseGroupSelected(course, group, $event)')
        expect(componentSource).toContain('Einzeltermine:')
        expect(componentSource).not.toContain('v-if="group.hasOccasional"')
        expect(componentSource).not.toContain('robot-course-item-detail--occasional')
        expect(componentSource).toContain('background: #eff6ff')
        expect(componentSource).toContain('robot-course-columns')
        expect(componentSource).toContain('Keine passenden Kurse gefunden.')
        expect(componentSource).toContain('Kurse')
    })

    it('resets selected quality counter checks before recalculating ranking', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            allQualityCriteriaCount: 2,
            qualityCounters: [
                {
                    key: 'free_days',
                    selected_value: 5,
                    selected_label: '5 freie Tage',
                    selected_reached: true,
                    count: 3,
                    total: 4,
                },
            ],
        }

        methods.resetQualityCounterSelection.call(ctx)

        expect(ctx.allQualityCriteriaCount).toBeNull()
        expect(ctx.qualityCounters).toEqual([
            {
                key: 'free_days',
                selected_value: null,
                selected_label: '-',
                selected_reached: false,
                count: 3,
                total: 4,
            },
        ])
    })

    it('serializes robot quality criteria for one calculation run', () => {
        const methods = (RobotTimetable as any).methods
        const criteria = [
            {
                key: 'free_days',
                label: 'Anzahl freie Tage',
                enabled: true,
                priority: 4,
                option: null,
                options: [],
            },
            {
                key: 'few_gaps',
                label: 'Wenig Lücken',
                enabled: false,
                priority: 2,
                option: null,
                options: [],
            },
        ]

        expect(methods.storageEvaluationCriteria.call(methods, criteria)).toEqual([
            {
                key: 'free_days',
                enabled: true,
                priority: 1,
                option: null,
            },
            {
                key: 'few_gaps',
                enabled: false,
                priority: 2,
                option: null,
            },
        ])
    })

    it('shows only saved enabled quality criteria on the robot page', () => {
        const methods = (RobotTimetable as any).methods
        const criteria = [
            {
                key: 'free_days',
                label: 'Anzahl freie Tage',
                enabled: true,
                priority: 1,
            },
            {
                key: 'few_gaps',
                label: 'Wenig Lücken',
                enabled: false,
                priority: 2,
            },
        ]

        expect(methods.enabledEvaluationCriteriaFromSettings.call(methods, criteria)).toEqual([
            {
                key: 'free_days',
                label: 'Anzahl freie Tage',
                enabled: true,
                priority: 1,
                option: null,
                options: [],
            },
        ])
    })

    it('updates displayed quality criteria immediately from settings changes', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            evaluationCriteria: [
                { key: 'free_days', label: 'Anzahl freie Tage', enabled: true, priority: 1 },
            ],
            evaluationCriteriaLoaded: false,
        }

        methods.onSettingsChanged.call(ctx, [
            { key: 'few_gaps', label: 'Wenig Lücken', enabled: true, priority: 1 },
            { key: 'free_days', label: 'Anzahl freie Tage', enabled: false, priority: 2 },
        ])

        expect(ctx.evaluationCriteriaLoaded).toBe(true)
        expect(ctx.evaluationCriteria).toEqual([
            {
                key: 'few_gaps',
                label: 'Wenig Lücken',
                enabled: true,
                priority: 1,
                option: null,
                options: [],
            },
        ])
    })

    it('applies parent-provided quality criteria to embedded robot summaries', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            evaluationCriteria: [],
            evaluationCriteriaLoaded: false,
        }

        methods.applyEvaluationCriteriaSettings.call(ctx, [
            { key: 'saturday_free', label: 'Samstag kein Unterricht', enabled: false, priority: 1 },
            { key: 'free_days', label: 'Anzahl freie Tage', enabled: true, priority: 2 },
        ])

        expect(ctx.evaluationCriteriaLoaded).toBe(true)
        expect(ctx.evaluationCriteria).toEqual([
            {
                key: 'free_days',
                label: 'Anzahl freie Tage',
                enabled: true,
                priority: 2,
                option: null,
                options: [],
            },
        ])
    })

    it('does not recalculate quality counters while settings are changed', () => {
        const methods = (RobotTimetable as any).methods
        const loadFullGreenTimetableCount = vi.fn()
        const ctx = {
            ...methods,
            evaluationCriteria: [],
            evaluationCriteriaLoaded: false,
            timetableCountResultsAvailable: true,
            timetableCalculationReady() {
                return true
            },
            loadFullGreenTimetableCount,
        }

        methods.applyEvaluationCriteriaSettings.call(ctx, [
            { key: 'free_days', label: 'Anzahl freie Tage', enabled: true, priority: 1 },
        ])

        expect(loadFullGreenTimetableCount).not.toHaveBeenCalled()
    })

    it('does not show stale quality counters after settings disable every criterion', () => {
        const computed = (RobotTimetable as any).computed
        const ctx = {
            evaluationCriteria: [],
            evaluationCriteriaLoaded: true,
            qualityCounters: [
                { key: 'saturday_free', label: 'Samstag kein Unterricht', enabled: true, count: 0, total: 0 },
                { key: 'free_days', label: 'Anzahl freie Tage', enabled: true, count: 0, total: 0 },
            ],
        }

        expect(computed.qualityCriterionRows.call(ctx)).toEqual([])
    })

    it('toggles a robot quality criterion without removing the visible timetable', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            evaluationCriteria: [
                { key: 'free_days', enabled: false, priority: 1, option: null, options: [] },
            ],
            loading: true,
            generationError: 'old',
            generationProblems: ['old'],
            generatedTimetables: [{ key: 'old' }],
            fullGreenTimetableCount: 2,
            greenTimetableCount: 3,
            qualityCounters: [{ key: 'free_days' }],
            fullGreenTimetableNumber: 1,
            greenTimetableNumber: 1,
            fullGreenTimetableCountError: 'old',
            normalizeTimetableResultCounters() {},
            loadFullGreenTimetableCountCalled: false,
            loadFullGreenTimetableCount() {
                this.loadFullGreenTimetableCountCalled = true
            },
        }

        methods.setEvaluationCriterionEnabled.call(ctx, { key: 'free_days' }, true)

        expect(ctx.evaluationCriteria[0].enabled).toBe(true)
        expect(ctx.generatedTimetables).toEqual([{ key: 'old' }])
        expect(ctx.qualityCounters).toEqual([])
        expect(ctx.loadFullGreenTimetableCountCalled).toBe(false)
    })

    it('keeps the create timetable button loading until creation finishes', async () => {
        const methods = (RobotTimetable as any).methods
        let finishCreation = () => {}
        const creationPromise = new Promise(resolve => {
            finishCreation = resolve
        })
        const ctx = {
            ...methods,
            loading: false,
            timetableCreateLoading: false,
            fullGreenTimetableCountLoading: false,
            selectedCourses: [{ key: 'D1' }],
            get timetableGenerationLoading() {
                return this.timetableCreateLoading || this.fullGreenTimetableCountLoading
            },
            loadFullGreenTimetableCount: vi.fn(() => creationPromise),
        }

        const createPromise = methods.createTimetables.call(ctx)

        expect(ctx.timetableCreateLoading).toBe(true)
        expect(ctx.timetableGenerationLoading).toBe(true)
        expect(ctx.loadFullGreenTimetableCount).toHaveBeenCalledWith({ preferFullGreen: true })

        finishCreation()
        await createPromise

        expect(ctx.timetableCreateLoading).toBe(false)
        expect(ctx.timetableGenerationLoading).toBe(false)
    })

    it('shows the count of timetables that match all active quality criteria', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            activeQualityCriterionRows: [
                { key: 'free_days', enabled: true, selected_reached: true },
                { key: 'few_gaps', enabled: true, selected_reached: true },
            ],
            allQualityCriteriaCount: 12,
            selectedTimetableResultType: 'green',
            fullGreenTimetableCount: 4,
            greenTimetableCount: 48,
            selectedTimetableResultCount: 48,
        }

        expect(methods.allQualityCriteriaCountLabel.call(ctx)).toBe('12 / 48')
        expect(methods.allQualityCriteriaCountDetail.call(ctx)).toBe('Ausgewählt: erfüllt')
    })

    it('shows only the fulfilled timetable count in the selected timetable quality summary', () => {
        const methods = (RobotTimetable as any).methods

        expect(methods.qualityCounterFulfilledCountLabel.call(methods, {
            enabled: true,
            count: 2160,
            total: 8400,
        }).replace(/\u00a0/gu, ' ')).toBe('2 160')
        expect(methods.qualityCounterFulfilledCountLabel.call(methods, {
            enabled: true,
        })).toBe('-')
    })

    it('calculates quality counters only when navigating to another timetable number', () => {
        const methods = (RobotTimetable as any).methods
        const loadFullGreenTimetableCount = vi.fn()
        const loadQualityCountersForSelectedTimetableType = vi.fn()
        const ctx = {
            ...methods,
            selectedTimetableResultType: 'green',
            greenTimetableNumber: 1,
            greenTimetableCount: 4,
            fullGreenTimetableCount: 0,
            conflictTimetableCount: 0,
            additionalCourseTimetableRequired: false,
            loadFullGreenTimetableCount,
            loadQualityCountersForSelectedTimetableType,
        }

        methods.moveTimetableResultCounter.call(ctx, 'green', 1)

        expect(ctx.greenTimetableNumber).toBe(2)
        expect(loadFullGreenTimetableCount).toHaveBeenCalledWith({ preserveQualityCounters: true })
        expect(loadQualityCountersForSelectedTimetableType).toHaveBeenCalledOnce()
    })

    it('shows the all quality criteria count against the active filtered timetable result count', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedTimetableResultType: 'green',
            greenTimetableCount: 124,
            activeQualityCriterionRows: [
                { key: 'free_days', enabled: true, selected_reached: true },
                { key: 'saturday_free', enabled: true, selected_reached: true },
            ],
            allQualityCriteriaCount: 7,
        }

        expect(methods.allQualityCriteriaCountLabel.call(ctx)).toBe('7 / 124')
    })

    it('pages through all-quality timetables first when available', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedTimetableResultType: 'full_green',
            fullGreenTimetableCount: 1080,
            additionalCourseTimetableRequired: false,
            activeQualityCriterionRows: [
                { key: 'saturday_free', enabled: true, selected_reached: true },
                { key: 'free_days', enabled: true, selected_reached: true },
            ],
            allQualityCriteriaCount: 2,
        }

        ctx.qualityCriteriaResultFilterActive = computed.qualityCriteriaResultFilterActive.call(ctx)
        ctx.selectedTimetableResultCount = computed.selectedTimetableResultCount.call(ctx)

        expect(ctx.qualityCriteriaResultFilterActive).toBe(true)
        expect(ctx.selectedTimetableResultCount).toBe(2)
        expect(methods.allQualityCriteriaCountLabel.call(ctx).replace(/\u00a0/gu, ' ')).toBe('2 / 1 080')
        expect(methods.timetableResultCounterLimitForCounter.call(ctx, 'full_green')).toBe(2)
    })

    it('shows when the selected robot timetable does not match all active quality criteria', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            activeQualityCriterionRows: [
                { key: 'free_days', enabled: true, selected_reached: true },
                { key: 'few_gaps', enabled: true, selected_reached: false },
            ],
            allQualityCriteriaCount: 12,
            selectedTimetableResultType: 'green',
            fullGreenTimetableCount: 4,
            greenTimetableCount: 48,
        }

        expect(methods.allQualityCriteriaCountDetail.call(ctx)).toBe('Ausgewählt: nicht erfüllt')
    })

    it('shows how many selected timetables accept checked additional courses', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            additionalCourseSelectedKeys: ['INF2', 'INF3'],
            selectedAdditionalCourses: [
                { key: 'INF2', code: 'INF2' },
                { key: 'INF3', code: 'INF3' },
            ],
            courseGroupItems(course) {
                return [{ title: `${course.code}-A` }]
            },
            selectedTimetableResultType: 'green',
            fullGreenTimetableCount: 4,
            greenTimetableCount: 48,
            additionalCourseTimetableCount: 12,
            selectedRobotTimetable: {
                additionalCoursesAccepted: true,
                acceptedAdditionalCourseCount: 2,
                missingAdditionalCourses: [],
            },
        }

        expect(computed.additionalCourseTimetableCountLabel.call(ctx)).toBe('12 / 48')
        expect(computed.additionalCourseTimetableCountCardValue.call(ctx)).toBe('12')
        expect(computed.additionalCourseTimetableCountCardVisible.call(ctx)).toBe(true)
        expect(computed.additionalCourseTimetableCountCardTitle.call(ctx)).toBe('Grüne Stundenpläne')
        expect(methods.additionalCoursesAcceptedBySelectedTimetable.call(ctx)).toBe(true)
        expect(methods.additionalCourseAcceptanceLabel.call(ctx)).toBe('2 / 2')

        ctx.selectedRobotTimetable = {
            additionalCoursesAccepted: false,
            acceptedAdditionalCourseCount: 1,
            missingAdditionalCourses: [{ key: 'INF3', code: 'INF3', name: 'Informatik 3' }],
        }

        expect(methods.additionalCourseAcceptanceLabel.call(ctx)).toBe('1 / 2')
        expect(methods.additionalCourseAcceptanceIcon.call(ctx)).toBe('mdi-alert-circle')
        expect(methods.additionalCourseAcceptanceColor.call(ctx)).toBe('error')
        expect(methods.selectedTimetableMissingAdditionalCourseLabels.call(ctx)).toBe('INF3 Informatik 3')
        expect(methods.additionalCourseMissingInSelectedTimetable.call(ctx, { key: 'INF3', code: 'INF3' })).toBe(true)
        expect(methods.additionalCourseMissingInSelectedTimetable.call(ctx, { key: 'INF2', code: 'INF2' })).toBe(false)
        expect(methods.additionalCourseMissingInSelectedTimetable.call(ctx, { key: 'M8', code: 'M8' })).toBe(false)

        ctx.additionalCourseSelectedKeys = ['INF2']

        expect(methods.additionalCourseMissingInSelectedTimetable.call(ctx, { key: 'INF3', code: 'INF3' })).toBe(false)
    })

    it('uses checked additional courses as a selectable timetable filter', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedAdditionalCourses: [{ key: 'INF2', code: 'INF2' }],
            additionalCourseTimetableRequired: false,
            additionalCourseTimetableCount: 12,
            fullGreenTimetableCountLoading: false,
            selectedTimetableResultType: 'green',
            greenTimetableNumber: 32,
            greenTimetableCount: 48,
            fullGreenTimetableCount: 4,
            loadFullGreenTimetableCountCalled: false,
            saved: false,
            timetableCalculationReady() {
                return true
            },
            loadFullGreenTimetableCount() {
                this.loadFullGreenTimetableCountCalled = true
            },
            saveLastRobotState() {
                this.saved = true
            },
        }

        expect(methods.isAdditionalCourseTimetableFilterSelectable.call(ctx)).toBe(true)

        methods.setAdditionalCourseTimetableRequired.call(ctx, true)

        expect(ctx.additionalCourseTimetableRequired).toBe(true)
        expect(ctx.greenTimetableNumber).toBe(1)
        expect(computed.selectedTimetableResultCount.call(ctx)).toBe(12)
        expect(ctx.loadFullGreenTimetableCountCalled).toBe(true)
        expect(ctx.saved).toBe(true)
    })

    it('requires selected additional courses when creating an extended timetable', async () => {
        const methods = (RobotTimetable as any).methods
        const loadFullGreenTimetableCount = vi.fn()
        const ctx = {
            ...methods,
            loading: false,
            timetableGenerationLoading: false,
            selectedCourses: [{ key: 'D1' }],
            selectedAdditionalCourses: [{ key: 'INF2' }],
            additionalCourseTimetableRequired: false,
            selectedTimetableResultType: 'green',
            fullGreenTimetableNumber: 4,
            greenTimetableNumber: 8,
            conflictTimetableNumber: 2,
            fullGreenTimetableCount: 12,
            greenTimetableCount: 24,
            conflictTimetableCount: 3,
            additionalCourseTimetableCount: null,
            additionalCourseExtensionActionHidden: false,
            loadFullGreenTimetableCount,
        }

        await methods.createTimetables.call(ctx, { requireAdditionalCourses: true })

        expect(ctx.additionalCourseTimetableRequired).toBe(true)
        expect(ctx.additionalCourseExtensionActionHidden).toBe(true)
        expect(ctx.greenTimetableNumber).toBe(1)
        expect(loadFullGreenTimetableCount).toHaveBeenCalledWith({ preferFullGreen: true })
        expect(ctx.timetableCreateLoading).toBe(false)
    })

    it('shows the extend timetable action again when additional course selection changes', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedRobotTimetable: { slots: {} },
            selectedAdditionalCourses: [{ key: 'D2' }],
            additionalCourseExtensionActionHidden: true,
            clearGeneratedTimetables: vi.fn(),
            saveLastRobotState: vi.fn(),
        }

        expect(computed.additionalCourseExtensionActionVisible.call(ctx)).toBe(false)

        methods.commitAdditionalCourseSelectionChange.call(ctx)

        expect(ctx.additionalCourseExtensionActionHidden).toBe(false)
        expect(computed.additionalCourseExtensionActionVisible.call(ctx)).toBe(true)
        expect(ctx.clearGeneratedTimetables).not.toHaveBeenCalled()
        expect(ctx.saveLastRobotState).toHaveBeenCalled()
    })

    it('switches to a conflict timetable when required additional courses are not visible in the selected timetable', () => {
        const methods = (RobotTimetable as any).methods
        const additionalCourse = { key: 'D2', code: 'D2', ttCodes: ['D2'], name: 'Deutsch 2' }
        const ctx = {
            ...methods,
            additionalCourseTimetableRequired: true,
            selectedTimetableResultType: 'full_green',
            conflictTimetableCount: 12,
            selectedAdditionalCourses: [additionalCourse],
        }

        expect(methods.shouldLoadConflictTimetableForRequiredAdditionalCourses.call(ctx, {
            slots: {
                '1-1': { key: 'D1', code: 'D1', ttCodes: ['D1'] },
            },
            occasionalAppointments: [],
        })).toBe(true)

        expect(methods.shouldLoadConflictTimetableForRequiredAdditionalCourses.call(ctx, {
            slots: {
                '1-1': {
                    key: 'D1',
                    code: 'D1',
                    conflicts: [
                        { key: 'D2', code: 'D2', ttCodes: ['D2'], isAdditionalCourse: true },
                    ],
                },
            },
            occasionalAppointments: [],
        })).toBe(false)
    })

    it('unselects additional courses when creating a new timetable', async () => {
        const methods = (RobotTimetable as any).methods
        const additionalCourse = { key: 'INF2', code: 'INF2', ttCodes: ['INF2'], name: 'Informatik 2', hours: 2 }
        const loadFullGreenTimetableCount = vi.fn()
        const ctx = {
            ...methods,
            loading: false,
            timetableGenerationLoading: false,
            selectedCourses: [{ key: 'D1' }],
            studentAdditionalCourses: [additionalCourse],
            additionalCourseSelectedKeys: ['INF2'],
            additionalCourseTimetableRequired: true,
            deselectedCourseGroupKeys: ['INF2|INF2-4Q-GOS'],
            selectedRobotTimetable: null,
            courseGroupItems() {
                return [{ title: 'INF2-4Q-GOS' }]
            },
            clearGeneratedTimetables() {
                this.cleared = true
            },
            saveLastRobotState() {
                this.saved = true
            },
            loadFullGreenTimetableCount,
        }

        await methods.createTimetables.call(ctx)

        expect(ctx.additionalCourseSelectedKeys).toEqual([])
        expect(ctx.additionalCourseTimetableRequired).toBe(false)
        expect(ctx.deselectedCourseGroupKeys).toEqual([])
        expect(ctx.cleared).toBe(true)
        expect(ctx.saved).toBe(true)
        expect(loadFullGreenTimetableCount).toHaveBeenCalledWith({ preferFullGreen: true })
    })

    it('resets the timetable selector when selecting another result type', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedTimetableResultType: 'full_green',
            additionalCourseTimetableRequired: false,
            fullGreenTimetableCount: 8,
            greenTimetableCount: 48,
            fullGreenTimetableNumber: 4,
            greenTimetableNumber: 32,
            loadFullGreenTimetableCountCalled: false,
            loadFullGreenTimetableCount() {
                this.loadFullGreenTimetableCountCalled = true
            },
        }

        methods.setSelectedTimetableResultType.call(ctx, 'green', true)

        expect(ctx.selectedTimetableResultType).toBe('green')
        expect(ctx.greenTimetableNumber).toBe(1)
        expect(ctx.fullGreenTimetableNumber).toBe(4)
        expect(ctx.loadFullGreenTimetableCountCalled).toBe(true)
    })

    it('keeps result type switches visually exclusive from the additional course filter', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedTimetableResultType: 'full_green',
            additionalCourseTimetableRequired: true,
            fullGreenTimetableCount: 8,
            greenTimetableCount: 48,
            fullGreenTimetableNumber: 4,
            greenTimetableNumber: 32,
            loadFullGreenTimetableCountCalled: false,
            loadFullGreenTimetableCount() {
                this.loadFullGreenTimetableCountCalled = true
            },
        }

        expect(methods.timetableResultCardSelected.call(ctx, 'full_green')).toBe(false)

        methods.setSelectedTimetableResultType.call(ctx, 'green', true)

        expect(ctx.additionalCourseTimetableRequired).toBe(false)
        expect(ctx.selectedTimetableResultType).toBe('green')
        expect(ctx.greenTimetableNumber).toBe(1)
        expect(methods.timetableResultCardSelected.call(ctx, 'green')).toBe(true)
        expect(ctx.loadFullGreenTimetableCountCalled).toBe(true)
    })

    it('explains when the additional course filter has no matching timetable', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedCourses: [{ key: 'D2', code: 'D2' }],
            selectedAdditionalCourses: [{ key: 'INF2', code: 'INF2', name: 'Informatik 2' }],
            activeQualityCriterionRows: [],
            selectedTimetableResultType: 'full_green',
            selectedTimetableResultCount: 0,
            fullGreenTimetableCount: 12,
            greenTimetableCount: 12,
            conflictTimetableCount: 0,
            additionalCourseTimetableRequired: true,
            additionalCourseTimetableCount: 0,
            allQualityCriteriaCount: null,
            fullGreenTimetableCountLoading: false,
            fullGreenTimetableCountError: '',
        }

        ctx.timetableCountResultsAvailable = computed.timetableCountResultsAvailable.call(ctx)
        ctx.selectedOptionsNoResultReasons = computed.selectedOptionsNoResultReasons.call(ctx)

        expect(ctx.selectedOptionsNoResultReasons).toEqual([
            'Der Zusatzkurs-Filter ist aktiv, aber kein voller grüner Stundenplan enthält alle gewählten Zusatzkurse: INF2 Informatik 2.',
        ])
        expect(computed.selectedOptionsNoResultAlertVisible.call(ctx)).toBe(true)
    })

    it('explains when active quality criteria have no matching timetable', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedCourses: [{ key: 'D2', code: 'D2' }],
            selectedAdditionalCourses: [],
            activeQualityCriterionRows: [
                { key: 'saturday_free', label: 'Samstag kein Unterricht', enabled: true },
                { key: 'free_days', label: 'Anzahl freie Tage', enabled: true },
            ],
            selectedTimetableResultType: 'green',
            selectedTimetableResultCount: 12,
            fullGreenTimetableCount: 0,
            greenTimetableCount: 12,
            conflictTimetableCount: 0,
            additionalCourseTimetableRequired: false,
            additionalCourseTimetableCount: 0,
            allQualityCriteriaCount: 0,
            fullGreenTimetableCountLoading: false,
            fullGreenTimetableCountError: '',
        }

        ctx.timetableCountResultsAvailable = computed.timetableCountResultsAvailable.call(ctx)
        ctx.selectedOptionsNoResultReasons = computed.selectedOptionsNoResultReasons.call(ctx)

        expect(ctx.selectedOptionsNoResultReasons).toEqual([
            'Kein grüner Stundenplan erfüllt alle aktiven Bewertungskriterien: Samstag kein Unterricht, Anzahl freie Tage.',
        ])
        expect(computed.selectedOptionsNoResultAlertVisible.call(ctx)).toBe(true)
    })

    it('falls back to conflict timetables when no green result exists', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedTimetableResultType: 'full_green',
            fullGreenTimetableCount: 0,
            greenTimetableCount: 0,
            conflictTimetableCount: 12,
        }

        expect(methods.autoSelectTimetableResultType.call(ctx)).toBe(true)
        expect(ctx.selectedTimetableResultType).toBe('conflict')
    })

    it('hides conflict timetables when green results are available', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            fullGreenTimetableCount: 1,
            greenTimetableCount: 0,
            conflictTimetableCount: 12,
        }

        expect(computed.hasGreenTimetableResults.call(ctx)).toBe(true)
        ctx.hasGreenTimetableResults = computed.hasGreenTimetableResults.call(ctx)

        expect(computed.showConflictTimetableResults.call(ctx)).toBe(false)
    })

    it('moves away from conflict timetables when green results become available', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedTimetableResultType: 'conflict',
            hasGreenTimetableResults: true,
            fullGreenTimetableCount: 0,
            greenTimetableCount: 3,
            conflictTimetableCount: 12,
        }

        expect(methods.autoSelectTimetableResultType.call(ctx)).toBe(true)
        expect(ctx.selectedTimetableResultType).toBe('green')
    })

    it('selects full green timetables first when creating timetables', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedTimetableResultType: 'green',
            hasGreenTimetableResults: true,
            fullGreenTimetableCount: 2,
            greenTimetableCount: 12,
            conflictTimetableCount: 0,
        }

        expect(methods.autoSelectTimetableResultType.call(ctx)).toBe(false)
        expect(ctx.selectedTimetableResultType).toBe('green')

        expect(methods.autoSelectTimetableResultType.call(ctx, { preferFullGreen: true })).toBe(true)
        expect(ctx.selectedTimetableResultType).toBe('full_green')
    })

    it('selects the additional-course timetable filter by default when matching timetables exist', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedAdditionalCourses: [{ key: 'INF2', code: 'INF2' }],
            selectedTimetableResultType: 'green',
            additionalCourseTimetableRequired: false,
            additionalCourseTimetableCount: 960,
            greenTimetableCount: 2160,
            greenTimetableNumber: 12,
        }

        expect(methods.autoSelectAdditionalCourseTimetableFilter.call(ctx)).toBe(true)
        expect(ctx.additionalCourseTimetableRequired).toBe(true)
        expect(ctx.greenTimetableNumber).toBe(1)

        ctx.additionalCourseTimetableRequired = false

        expect(methods.autoSelectAdditionalCourseTimetableFilter.call(ctx, {
            skipAdditionalCourseDefaultSelection: true,
        })).toBe(false)
        expect(ctx.additionalCourseTimetableRequired).toBe(false)
    })

    it('allows backend red timetables to stay selectable next to full green results', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            backendVariationCountsAvailable: true,
            selectedTimetableResultType: 'conflict',
            fullGreenTimetableCount: 10,
            greenTimetableCount: 0,
            conflictTimetableCount: 2,
            fullGreenTimetableCountLoading: false,
            conflictTimetableNumber: 1,
            loadFullGreenTimetableCountCalled: false,
            loadFullGreenTimetableCount() {
                this.loadFullGreenTimetableCountCalled = true
            },
        }

        expect(methods.isTimetableResultTypeSelectable.call(ctx, 'conflict')).toBe(true)
        expect(methods.autoSelectTimetableResultType.call(ctx)).toBe(false)

        methods.selectBackendCountCard.call(ctx, 'full_green')

        expect(ctx.selectedTimetableResultType).toBe('full_green')
        expect(ctx.loadFullGreenTimetableCountCalled).toBe(true)
    })

    it('allows backend green timetables to be selected between full green and red results', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            backendVariationCountsAvailable: true,
            selectedTimetableResultType: 'full_green',
            fullGreenTimetableCount: 10,
            greenTimetableCount: 3,
            conflictTimetableCount: 2,
            fullGreenTimetableCountLoading: false,
            greenTimetableNumber: 1,
            loadFullGreenTimetableCountCalled: false,
            loadFullGreenTimetableCount() {
                this.loadFullGreenTimetableCountCalled = true
            },
        }

        expect(methods.isTimetableResultTypeSelectable.call(ctx, 'green')).toBe(true)
        expect(methods.backendCountCardSelectable.call(ctx, 'green')).toBe(true)

        methods.selectBackendCountCard.call(ctx, 'green')

        expect(ctx.selectedTimetableResultType).toBe('green')
        expect(ctx.loadFullGreenTimetableCountCalled).toBe(true)
    })

    it('does not select green result types without available timetables', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedTimetableResultType: 'conflict',
            fullGreenTimetableCount: 0,
            greenTimetableCount: 0,
            conflictTimetableCount: 4,
            showConflictTimetableResults: true,
            generatedTimetables: [{ key: 'current' }],
            loadFullGreenTimetableCountCalled: false,
            loadFullGreenTimetableCount() {
                this.loadFullGreenTimetableCountCalled = true
            },
        }

        expect(methods.isTimetableResultTypeSelectable.call(ctx, 'full_green')).toBe(false)
        expect(methods.isTimetableResultTypeSelectable.call(ctx, 'green')).toBe(false)

        methods.setSelectedTimetableResultType.call(ctx, 'full_green', true)
        methods.setSelectedTimetableResultType.call(ctx, 'green', true)

        expect(ctx.selectedTimetableResultType).toBe('conflict')
        expect(ctx.generatedTimetables).toEqual([{ key: 'current' }])
        expect(ctx.loadFullGreenTimetableCountCalled).toBe(false)
    })

    it('edits the robot selection through a draft dialog', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectionDialogOpen: false,
            selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            selectionDraft: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            semesterOptions: computed.semesterOptions.call({}),
            religionOptions: computed.religionOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            clearGeneratedTimetables() {},
            saveLastRobotState() {},
        }

        methods.openSelectionDialog.call(ctx)

        expect(ctx.selectionDialogOpen).toBe(true)
        expect(ctx.selectionDraft).toEqual(ctx.selection)

        ctx.selectionDraft.semester = 3
        ctx.selectionDraft.religion = 'Rk'
        ctx.selectionDraft.branch = 'gymnasial'
        ctx.selectionDraft.artsSubject = 'BE'
        ctx.selectionDraft.language = 'F'

        methods.updateSelection.call(ctx)

        expect(ctx.selectionDialogOpen).toBe(false)
        expect(ctx.selection).toEqual({
            semester: 3,
            religion: 'Rk',
            branch: 'gymnasial',
            artsSubject: 'BE',
            language: 'F',
        })
        expect(computed.selectedSummary.call(ctx).map(item => item.value)).toEqual([
            'Semester 3',
            'Rk - Religion katholisch',
            'F - Französisch',
            'Gymnasialer Zweig',
            'BE - Bildnerische Erziehung',
        ])
    })

    it('edits robot time constraints through a draft dialog', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            constraintsDialogOpen: false,
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2, 3, 4],
            },
            constraintsDraft: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2, 3, 4],
            },
            weekdayOptions: computed.weekdayOptions.call({}),
            timeOptions: [
                { title: '1. Stunde', shortTitle: '1. Stunde', value: 1 },
                { title: '2. Stunde', shortTitle: '2. Stunde', value: 2 },
                { title: '3. Stunde', shortTitle: '3. Stunde', value: 3 },
                { title: '4. Stunde', shortTitle: '4. Stunde', value: 4 },
            ],
            clearGeneratedTimetables() {},
            saveLastRobotState() {},
        }
        ctx.weekdayTimeOptions = computed.weekdayTimeOptions.call(ctx)
        ctx.unavailableWeekdays = computed.unavailableWeekdays.call(ctx)
        ctx.unavailableTimes = computed.unavailableTimes.call(ctx)
        ctx.selectedConstraintSummary = computed.selectedConstraintSummary.call(ctx)

        methods.openConstraintsDialog.call(ctx)

        expect(ctx.constraintsDialogOpen).toBe(true)
        expect(ctx.constraintsDraft).toEqual(ctx.constraints)

        methods.toggleDraftConstraint.call(ctx, 'availableWeekdays', 6)
        methods.toggleDraftConstraint.call(ctx, 'availableTimes', 4)
        methods.toggleDraftWeekdayTime.call(ctx, 2, 3)

        expect(ctx.constraints).toEqual({
            availableWeekdays: [1, 2, 3, 4, 5, 6],
            excludedWeekdayTimes: [],
            availableTimes: [1, 2, 3, 4],
        })

        methods.updateConstraints.call(ctx)

        expect(ctx.constraintsDialogOpen).toBe(false)
        expect(ctx.constraints).toEqual({
            availableWeekdays: [1, 2, 3, 4, 5],
            excludedWeekdayTimes: ['2-3'],
            availableTimes: [1, 2, 3],
        })

        ctx.unavailableWeekdays = computed.unavailableWeekdays.call(ctx)
        ctx.unavailableTimes = computed.unavailableTimes.call(ctx)

        expect(computed.selectedConstraintSummary.call(ctx).map(item => item.value)).toEqual([
            'Samstag',
            'Dienstag, 3. Stunde',
            '4. Stunde',
        ])
    })

    it('clears the selected student directly', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: { student_code: '100' },
            studentSelection: {
                studentCode: '100',
            },
            studentSelectionDraft: {
                studentCode: '100',
            },
            studentSelectionDefaultsPendingCode: '100',
            additionalCourseSelectedKeys: ['D2'],
            studentCompletedCoursesExpanded: true,
            studentDialogOpen: true,
            studentSearch: 'Mayr',
            defaultSelectionApplied: false,
            generatedCleared: false,
            saved: false,
            applySelectedStudentDefaultSelection() {
                this.defaultSelectionApplied = true
            },
            clearGeneratedTimetables() {
                this.generatedCleared = true
            },
            saveLastRobotState() {
                this.saved = true
            },
        }

        methods.clearStudentSelection.call(ctx)

        expect(ctx.studentSelection).toEqual({ studentCode: null })
        expect(ctx.studentSelectionDraft).toEqual({ studentCode: null })
        expect(ctx.studentSelectionDefaultsPendingCode).toBeNull()
        expect(ctx.additionalCourseSelectedKeys).toEqual([])
        expect(ctx.studentCompletedCoursesExpanded).toBe(false)
        expect(ctx.studentDialogOpen).toBe(false)
        expect(ctx.studentSearch).toBe('')
        expect(ctx.defaultSelectionApplied).toBe(true)
        expect(ctx.generatedCleared).toBe(true)
        expect(ctx.saved).toBe(true)
    })

    it('focuses the student search field when editing the student', () => {
        const methods = (RobotTimetable as any).methods
        const focusCalls: string[] = []
        const ctx = {
            ...methods,
            studentSelection: {
                studentCode: '100',
            },
            studentSelectionDraft: {
                studentCode: null,
            },
            studentDialogOpen: false,
            studentSearch: 'Grassl',
            $refs: {
                studentSearchField: {
                    focus() {
                        focusCalls.push('field')
                    },
                    $el: {
                        querySelector(selector: string) {
                            expect(selector).toBe('input')

                            return {
                                focus() {
                                    focusCalls.push('input')
                                },
                            }
                        },
                    },
                },
            },
            $nextTick(callback: () => void) {
                callback()
            },
        }

        methods.openStudentDialog.call(ctx)

        expect(ctx.studentSelectionDraft).toEqual({ studentCode: '100' })
        expect(ctx.studentSearch).toBe('')
        expect(ctx.studentDialogOpen).toBe(true)
        expect(focusCalls).toEqual(['field', 'input'])
    })

    it('selects the only matching student before updating on enter', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            studentSearch: 'grassl',
            studentSelectionDraft: {
                studentCode: null,
            },
            robotStudents: [
                { student_code: '100', class: '4Q', last_name: 'GRASSL', first_name: 'Tobias' },
                { student_code: '200', class: '4Q', last_name: 'MAYR', first_name: 'Anna' },
            ],
            normalizedStudentCode(value) {
                return value === null || value === undefined || value === '' ? null : String(value)
            },
            studentOptionTitle(student) {
                return [student.class, student.last_name, student.first_name].filter(Boolean).join(' ')
            },
            applyStudentSelection(studentCode) {
                this.appliedStudentCode = studentCode
            },
        }

        Object.defineProperty(ctx, 'normalizedStudentSearch', {
            get() {
                return computed.normalizedStudentSearch.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'studentSearchReady', {
            get() {
                return computed.studentSearchReady.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'filteredStudentResults', {
            get() {
                return computed.filteredStudentResults.call(ctx)
            },
        })

        methods.submitStudentSearch.call(ctx)

        expect(ctx.studentSelectionDraft.studentCode).toBe('100')
        expect(ctx.appliedStudentCode).toBe('100')
    })

    it('removes single time exclusions when a whole hour is disabled', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            constraintsDraft: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2, 3, 4],
            },
        }

        methods.toggleDraftWeekdayTime.call(ctx, 2, 3)
        expect(ctx.constraintsDraft.excludedWeekdayTimes).toEqual(['2-3'])

        methods.toggleDraftConstraint.call(ctx, 'availableTimes', 3)

        expect(ctx.constraintsDraft).toEqual({
            availableWeekdays: [1, 2, 3, 4, 5, 6],
            excludedWeekdayTimes: [],
            availableTimes: [1, 2, 4],
        })
    })

    it('formats multiple disabled single times on separate lines', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            weekdayOptions: computed.weekdayOptions.call({}),
            timeOptions: [
                { title: '15. Stunde (21:10-21:55)', shortTitle: '15. Stunde (21:10-21:55)', value: 15 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: ['1-15', '2-15', '3-15'],
                availableTimes: [15],
            },
            unavailableWeekdays: [],
            unavailableTimes: [],
        }
        ctx.weekdayTimeOptions = computed.weekdayTimeOptions.call(ctx)

        const excludedTimesSummary = computed.selectedConstraintSummary.call(ctx)
            .find(item => item.key === 'excludedWeekdayTimes')

        expect(excludedTimesSummary?.value).toBe([
            'Montag, 15. Stunde (21:10-21:55)',
            'Dienstag, 15. Stunde (21:10-21:55)',
            'Mittwoch, 15. Stunde (21:10-21:55)',
        ].join('\n'))
    })

    it('formats multiple unavailable days inline with commas', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            weekdayOptions: computed.weekdayOptions.call({}),
            timeOptions: [],
            constraints: {
                availableWeekdays: [1, 2, 3, 4],
                excludedWeekdayTimes: [],
                availableTimes: [],
            },
            unavailableWeekdays: [5, 6],
            unavailableTimes: [],
        }
        ctx.weekdayTimeOptions = computed.weekdayTimeOptions.call(ctx)

        const unavailableDaysSummary = computed.selectedConstraintSummary.call(ctx)
            .find(item => item.key === 'unavailableWeekdays')

        expect(unavailableDaysSummary?.value).toBe('Freitag, Samstag')
    })

    it('formats multiple unused hours on separate lines', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            weekdayOptions: computed.weekdayOptions.call({}),
            timeOptions: [
                { title: '14. Stunde (20:25-21:10)', shortTitle: '14. Stunde (20:25-21:10)', value: 14 },
                { title: '15. Stunde (21:10-21:55)', shortTitle: '15. Stunde (21:10-21:55)', value: 15 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: [],
            },
            unavailableWeekdays: [],
            unavailableTimes: [14, 15],
        }
        ctx.weekdayTimeOptions = computed.weekdayTimeOptions.call(ctx)

        const unusedHoursSummary = computed.selectedConstraintSummary.call(ctx)
            .find(item => item.key === 'unavailableTimes')

        expect(unusedHoursSummary?.value).toBe([
            '14. Stunde (20:25-21:10)',
            '15. Stunde (21:10-21:55)',
        ].join('\n'))
    })

    it('compacts consecutive unused hour ranges', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            weekdayOptions: computed.weekdayOptions.call({}),
            timeOptions: [
                { title: '1. Stunde (09:00-09:45)', shortTitle: '1. Stunde (09:00-09:45)', value: 1 },
                { title: '2. Stunde (09:45-10:30)', shortTitle: '2. Stunde (09:45-10:30)', value: 2 },
                { title: '3. Stunde (10:40-11:25)', shortTitle: '3. Stunde (10:40-11:25)', value: 3 },
                { title: '4. Stunde (11:25-12:10)', shortTitle: '4. Stunde (11:25-12:10)', value: 4 },
                { title: '5. Stunde (13:00-13:45)', shortTitle: '5. Stunde (13:00-13:45)', value: 5 },
                { title: '6. Stunde (13:45-14:30)', shortTitle: '6. Stunde (13:45-14:30)', value: 6 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: [],
            },
            unavailableWeekdays: [],
            unavailableTimes: [1, 2, 3, 4, 5, 6],
        }
        ctx.weekdayTimeOptions = computed.weekdayTimeOptions.call(ctx)

        const unusedHoursSummary = computed.selectedConstraintSummary.call(ctx)
            .find(item => item.key === 'unavailableTimes')

        expect(unusedHoursSummary?.value).toBe('1.-6. Stunde (09:00 - 14:30)')
    })

    it('saves and restores robot timetable settings', () => {
        const methods = (RobotTimetable as any).methods
        const storedItems = new Map<string, string>()
        const storage = {
            getItem(key: string) {
                return storedItems.get(key) || null
            },
            setItem(key: string, value: string) {
                storedItems.set(key, value)
            },
        }
        const ctx = {
            ...methods,
            config: { selected_schoolyear: { id: 42 } },
            selection: {
                semester: 3,
                religion: 'Rk',
                branch: 'gymnasial',
                artsSubject: 'BE',
                language: 'F',
            },
            constraints: {
                availableWeekdays: [1, 2, 3, 4],
                excludedWeekdayTimes: ['2-3'],
                availableTimes: [1, 2, 3],
            },
            availableCourses: [
                { key: 'D1' },
                { key: 'GW1' },
                { key: 'INF1' },
            ],
            deselectedCourseKeys: ['GW1'],
            deselectedCourseGroupKeys: ['D1|D1-1C-GOS'],
            robotStateRestoring: false,
            courseGroupItems(course) {
                if (course.key === 'D1') {
                    return [{ title: 'D1-1C-GOS' }, { title: 'D1-1C-HER' }]
                }

                return [{ title: `${course.key}-1C-GOS` }]
            },
            robotStorage() {
                return storage
            },
        }

        methods.saveLastRobotState.call(ctx)

        expect(storedItems.has(methods.robotStorageKey.call(ctx, 42))).toBe(true)
        expect(storedItems.has(methods.robotStorageKey.call(ctx, 'default'))).toBe(true)

        const restoredCtx = {
            ...methods,
            config: { selected_schoolyear: { id: 42 } },
            selection: methods.defaultRobotState().selection,
            constraints: methods.defaultRobotState().constraints,
            availableCourses: [
                { key: 'D1' },
                { key: 'GW1' },
                { key: 'INF1' },
            ],
            deselectedCourseKeys: [],
            deselectedCourseGroupKeys: [],
            robotStateRestoring: false,
            courseGroupItems(course) {
                if (course.key === 'D1') {
                    return [{ title: 'D1-1C-GOS' }, { title: 'D1-1C-HER' }]
                }

                return [{ title: `${course.key}-1C-GOS` }]
            },
            robotStorage() {
                return storage
            },
        }

        methods.restoreLastRobotState.call(restoredCtx)

        expect(restoredCtx.selection).toEqual(ctx.selection)
        expect(restoredCtx.constraints).toEqual(ctx.constraints)
        expect(restoredCtx.deselectedCourseKeys).toEqual(['GW1'])
        expect(restoredCtx.deselectedCourseGroupKeys).toEqual(['D1|D1-1C-GOS'])
    })

    it('overtakes the displayed robot timetable into the overview storage and opens overview', () => {
        const methods = (RobotTimetable as any).methods
        const storedItems = new Map<string, string>()
        const routerPush = vi.fn()
        const ctx = {
            ...methods,
            config: { selected_schoolyear: { id: 42 } },
            selected_schoolyear: null,
            selection: {
                semester: 6,
                religion: 'ETH',
                language: 'S',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            subjectMappings: [],
            selectedStudent: {
                student_code: '100',
                class: '4Q',
                last_name: 'GRASSL',
                first_name: 'Tobias',
                school_level: '11',
                attendance_year: '2',
            },
            selectedStudentLabel: '4Q · GRASSL Tobias · Semester 6',
            studentCompletedCourses: [
                { subject: 'ETH1', grade: '1' },
            ],
            studentMissingCourses: [
                { key: 'CH2', code: 'CH2', name: 'Chemie 2', hours: 3 },
            ],
            studentPlannedCourses: [
                { key: 'S5', code: 'S5', name: 'Spanisch 5', hours: 4 },
            ],
            studentAdditionalCourses: [
                { key: 'PP2', code: 'PP2', name: 'Philosophie/Psychologie 2', hours: 2 },
            ],
            configuredCourseGroups: [
                {
                    key: 'spa5-single',
                    class_name: 'SPA5-4A-APP',
                    course: 'SPA5',
                    subject: 'SPA',
                    weekday: 6,
                    hour: 11,
                },
            ],
            selectedRobotTimetable: {
                slots: {
                    '1-1': {
                        courseGroup: { key: 'ch2-regular', weekday: 1, hour: 1 },
                        conflicts: [
                            { courseGroup: { key: 'pp2-conflict', weekday: 5, hour: 1 } },
                        ],
                    },
                },
                selectedOccasionalAppointmentGroups: {
                    S5: 'S5|SPA5-4A-APP|S5 - SPA5-4A-APP',
                },
                occasionalAppointments: [
                    {
                        key: 'single-spa5',
                        code: 'S5',
                        courseKey: 'S5',
                        sourceLabel: 'SPA5-4A-APP',
                        weekday: 6,
                        hour: 11,
                    },
                    {
                        key: 'single-s3',
                        code: 'S3',
                        courseKey: 'S3',
                        sourceLabel: 'SPA3-4A-APP',
                        weekday: 5,
                        hour: 10,
                    },
                ],
            },
            robotStorage() {
                return {
                    setItem(key: string, value: string) {
                        storedItems.set(key, value)
                    },
                }
            },
            $router: {
                push: routerPush,
            },
        }

        methods.overtakeSelectedTimetableToOverview.call(ctx)

        const storedState = JSON.parse(storedItems.get(
            'students-timetables:overview:last-timetable:42',
        ) || '{}')

        expect(storedState).toEqual({
            activeCourseGroupFilterKeys: ['ch2-regular', 'pp2-conflict', 'spa5-single'],
            selectedRecurrenceWeeks: {
                1: 'all_dates',
                2: 'all_dates',
            },
            expandedRecurrenceWeeks: {
                1: false,
                2: false,
            },
            showExtraDatesInSelectedWeeks: {
                1: false,
                2: false,
            },
            showSaturday: true,
            selection: {
                semester: 6,
                religion: 'ETH',
                language: 'S',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            transferredStudentContext: {
                student: {
                    studentCode: '100',
                    label: '4Q · GRASSL Tobias · Semester 6',
                    semesterLabel: 'Semester 6',
                },
                courses: {
                    completed: [
                        {
                            key: 'completed-ETH1-1',
                            code: 'ETH1',
                            label: 'ETH1',
                            meta: '1',
                        },
                    ],
                    missing: [
                        {
                            key: 'CH2',
                            code: 'CH2',
                            name: 'Chemie 2',
                            label: 'CH2',
                            meta: '3 Std.',
                        },
                    ],
                    planned: [
                        {
                            key: 'S5',
                            code: 'S5',
                            name: 'Spanisch 5',
                            label: 'S5',
                            meta: '4 Std.',
                        },
                    ],
                    additional: [
                        {
                            key: 'PP2',
                            code: 'PP2',
                            name: 'Philosophie/Psychologie 2',
                            label: 'PP2',
                            meta: '2 Std.',
                        },
                    ],
                },
            },
        })
        expect(routerPush).toHaveBeenCalledWith({ path: '/admin/students-timetables/timetable/overview' })
    })

    it('restores selected courses by course code when saved keys changed', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            availableCourses: [
                { key: 'new-d5-key', code: 'D5' },
                { key: 'new-f4-key', code: 'F4' },
                { key: 'new-ch2-key', code: 'CH2' },
            ],
        }

        expect(methods.restoredDeselectedCourseKeys.call(ctx, {
            selectedCourseKeys: ['old-d5-key', 'old-f4-key'],
            selectedCourseCodes: ['D5', 'F4'],
        })).toEqual(['new-ch2-key'])
    })

    it('reconciles restored robot selection with the restored student defaults', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            robotStudents: [
                { student_code: '100', school_level: '09', attendance_year: '2' },
            ],
            studentCompletedCourses: [
                { subject: 'F1', grade: '5' },
                { subject: 'RK1', grade: '5' },
                { subject: 'BE1', grade: 'B' },
            ],
            selection: methods.defaultRobotState().selection,
            constraints: methods.defaultRobotState().constraints,
            availableCourses: [],
            deselectedCourseKeys: [],
            deselectedCourseGroupKeys: [],
            additionalCourseSelectedKeys: [],
            robotStateRestoring: false,
        }

        methods.applyRobotState.call(ctx, {
            ...methods.defaultRobotState.call(ctx),
            student: { studentCode: '100' },
            selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'gymnasial',
                artsSubject: 'ME',
                language: 'L',
            },
        })

        expect(ctx.selection).toEqual({
            semester: 2,
            religion: 'Rk',
            branch: 'wirtschaftskundlich',
            artsSubject: 'BE',
            language: 'F',
        })
        expect(ctx.studentSelection).toEqual({ studentCode: '100' })
        expect(ctx.studentSelectionDefaultsPendingCode).toBe('100')
    })

    it('restores robot settings from selected schoolyear and default storage keys', () => {
        const methods = (RobotTimetable as any).methods
        const storedItems = new Map<string, string>()
        const storage = {
            getItem(key: string) {
                return storedItems.get(key) || null
            },
            setItem(key: string, value: string) {
                storedItems.set(key, value)
            },
        }
        const ctx = {
            ...methods,
            config: null,
            selected_schoolyear: { id: 42 },
            selection: methods.defaultRobotState().selection,
            constraints: methods.defaultRobotState().constraints,
            availableCourses: [
                { key: 'd5-key', code: 'D5' },
                { key: 'f4-key', code: 'F4' },
            ],
            deselectedCourseKeys: [],
            robotStateRestoring: false,
            robotStorage() {
                return storage
            },
        }

        storedItems.set(methods.robotStorageKey.call(ctx, 'default'), JSON.stringify({
            ...methods.defaultRobotState.call(ctx),
            selectedCourseCodes: ['F4'],
        }))

        methods.restoreLastRobotState.call(ctx)

        expect(methods.robotSchoolyearId.call(ctx)).toBe(42)
        expect(ctx.deselectedCourseKeys).toEqual(['d5-key'])
    })

    it('treats imported TT Grp course slots as alternatives', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'INF', code: 'INF', name: 'Informatik', branch: 'alle', hours: 2 },
            ],
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', course: 'INF', subject: 'INF', weekday: 1, hour: 13 },
                { key: 'inf-fr', class_name: 'INF1-Grp2-KRO', course: 'INF', subject: 'INF', weekday: 5, hour: 12 },
                { key: 'inf2-wrong', class_name: 'INF2-7C-STRA', weekday: 4, hour: 1 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables).toHaveLength(2)
        expect(ctx.generatedTimetables.map(timetable => Object.keys(timetable.slots).sort())).toEqual([
            ['1-13'],
            ['5-12'],
        ])
        expect(ctx.generatedTimetables.map(timetable => Object.values(timetable.slots)[0].sourceLabel)).toEqual([
            'INF1-Grp1-KRO',
            'INF1-Grp2-KRO',
        ])
        expect(ctx.generatedTimetables.map(timetable => Object.values(timetable.slots)[0].sourceLabel)).not.toContain(
            'INF1-Grp1-KRO + INF1-Grp2-KRO',
        )
        expect(ctx.generatedTimetables[0].slots['4-1']).toBeUndefined()
    })

    it('groups same-slot course alternatives inside one generated timetable cell', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                { key: 'd-gos', class_name: 'D1-1C-GOS', course: 'D1', subject: 'D', weekday: 1, hour: 1 },
                { key: 'd-her', class_name: 'D1-1C-HER', course: 'D1', subject: 'D', weekday: 1, hour: 1 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables).toHaveLength(1)
        expect(ctx.generatedTimetables[0].slots['1-1'].code).toBe('D1')
        expect(ctx.generatedTimetables[0].slots['1-1'].alternativeLabels).toEqual([
            'D1-1C-GOS',
            'D1-1C-HER',
        ])
        expect(methods.generatedSlotDetails.call(ctx, ctx.generatedTimetables[0].slots['1-1'])).toBe(
            'D1-1C-GOS\noder\nD1-1C-HER',
        )
    })

    it('returns more than the first ten generated timetable possibilities', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: Array.from({ length: 11 }, (value, index) => ({
                key: `d-${index + 1}`,
                class_name: `D1-1C-${index + 1}`,
                course: 'D1',
                subject: 'D',
                weekday: 1,
                hour: index + 1,
            })),
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generatedTimetables).toHaveLength(11)
    })

    it('does not combine alternative Grp options to satisfy course hours', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', course: 'INF', subject: 'INF', weekday: 1, hour: 13 },
                { key: 'inf-fr', class_name: 'INF1-Grp2-KRO', course: 'INF', subject: 'INF', weekday: 5, hour: 12 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        const options = methods.timetableOptionsForCourse.call(ctx, {
            key: 'INF',
            code: 'INF',
            name: 'Informatik',
            hours: 2,
        })

        expect(options.map(option => option.label)).toEqual(['INF1-Grp1-KRO', 'INF1-Grp2-KRO'])
    })

    it('does not combine different module course options with the same leading code', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            configuredCourseGroups: [
                { key: 'eth-ck', class_name: 'ETH1-1CK-PLÖ', course: 'ETH', subject: 'ETH', weekday: 2, hour: 11 },
                { key: 'eth-ru', class_name: 'ETH1-1RU-PLÖC', course: 'ETH', subject: 'ETH', weekday: 5, hour: 7 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        const options = methods.timetableOptionsForCourse.call(ctx, {
            key: 'ETH1',
            code: 'ETH1',
            name: 'Ethik',
            hours: 2,
        })

        expect(options.map(option => option.label)).toEqual(['ETH1-1CK-PLÖ', 'ETH1-1RU-PLÖC'])
        expect(options.map(option => option.label)).not.toContain('ETH1-1CK-PLÖ + ETH1-1RU-PLÖC')
    })

    it('matches subject overview codes against imported TT aliases', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }

        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'GWB1-1C-HÖF',
            course: 'GWB',
            subject: 'GWB',
        }, {
            code: 'GW1',
            ttCode: 'GWB1',
        })).toBe(true)

        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'LPT-1CK-DREI',
            course: 'LET',
            subject: 'LET',
        }, {
            code: 'LPT',
            ttCode: 'LET',
        })).toBe(true)
    })

    it('does not match a different numbered module through a broad TT subject code', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            subjectMappings: [
                { json_subject: 'ÖKO', tt_subject: 'OKON', is_active: true },
            ],
        }
        const course = {
            code: 'ÖKO2 / ÖKO3',
            ttCodes: ['OKON2', 'OKON3'],
        }

        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'OKON2- BIE',
            course: 'OKON',
            subject: 'OKON',
        }, course)).toBe(true)

        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'OKON1- PLA',
            course: 'OKON',
            subject: 'OKON',
        }, course)).toBe(false)

        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'ÖKO1- PLA',
            course: 'ÖKO',
            subject: 'ÖKO',
        }, course)).toBe(false)
    })

    it('expands split ÖKO course codes to numbered OKON timetable aliases through mappings', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selection: {
                religion: 'ETH',
                language: 'L',
            },
            subjectMappings: [
                { json_subject: 'ÖKO', tt_subject: 'OKON', is_active: true },
            ],
        }

        expect(methods.selectedCourseTimetableCodes.call(ctx, {
            json_code: 'ÖKO2 / ÖKO3',
            json_subject: 'ÖKO',
        })).toEqual(['OKON2', 'OKON3'])

        expect(methods.courseCodeAliases.call(ctx, {
            code: 'ÖKO2 / ÖKO3',
        })).toEqual(['ÖKO2', 'ÖKO3'])
    })

    it('uses the configured Fach-Zuordnung for TT matching', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selection: {
                language: 'S',
                religion: 'ETH',
            },
            subjectMappings: [
                { json_subject: 'GW', tt_subject: 'GWB', is_active: true },
                { json_subject: 'LPT', tt_subject: 'LET', is_active: true },
                { json_subject: 'S', tt_subject: 'SPA', is_active: true },
                { json_subject: 'ME', tt_subject: 'MU', is_active: false },
            ],
        }

        expect(methods.selectedCourseTimetableCodes.call(ctx, {
            json_code: 'GW1',
            json_subject: 'GW',
        })).toEqual(['GWB1'])

        expect(methods.selectedCourseTimetableCodes.call(ctx, {
            json_code: 'LPT',
            json_subject: 'LPT',
        })).toEqual(['LET'])

        expect(methods.selectedCourseTimetableCodes.call(ctx, {
            json_code: 'L/F/S3',
            json_subject: 'L/F/S',
        })).toEqual(['SPA3', 'S3'])
    })

    it('uses exact mapped TT codes when module expansion would miss the imported course', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            subjectMappings: [
                { json_subject: 'LPT', tt_subject: 'LET', is_active: true },
            ],
            configuredCourseGroups: [
                { key: 'lpt', class_name: 'LPT-1CK-DREI', course: 'LET', subject: 'LET', weekday: 4, hour: 10 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }
        const timetableCodes = methods.selectedCourseTimetableCodes.call(ctx, {
            json_code: 'LPT1',
            json_subject: 'LPT',
        })

        expect(timetableCodes).toEqual(['LET1', 'LET'])
        expect(methods.timetableOptionsForCourse.call(ctx, {
            key: 'LPT1',
            code: 'LPT1',
            name: 'LPT',
            hours: 1,
            ttCodes: timetableCodes,
        }).map(option => option.label)).toEqual(['LPT-1CK-DREI'])
    })

    it('treats language choices as alternatives instead of common aliases', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selection: {
                language: 'F',
                religion: 'ETH',
            },
            subjectMappings: [],
        }
        const languageSubject = {
            json_code: 'L/F/S1',
            json_subject: 'L/F/S',
            tt_subject: 'L/F/S',
        }
        const timetableCodes = methods.selectedCourseTimetableCodes.call(ctx, languageSubject)

        expect(methods.selectedCourseCode.call(ctx, languageSubject)).toBe('F1')
        expect(timetableCodes).toEqual(['F1'])
        expect(methods.courseCodeAliases.call(ctx, {
            code: 'F1',
            ttCodes: timetableCodes,
        })).toEqual(['F1'])
        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'F1-7A-MAY',
            course: 'F',
            subject: 'F',
        }, {
            code: 'F1',
            ttCodes: timetableCodes,
        })).toBe(true)
        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'L1-7A-MAY',
            course: 'L',
            subject: 'L',
        }, {
            code: 'F1',
            ttCodes: timetableCodes,
        })).toBe(false)
        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'SPA1-7A-MAY',
            course: 'SPA',
            subject: 'SPA',
        }, {
            code: 'S1',
            ttCodes: ['S1'],
        })).toBe(true)
        expect(methods.courseCodeAliases.call(ctx, {
            code: 'S3',
            ttCodes: ['S3'],
        })).toEqual(['S3', 'SPA3'])
        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'SPA3-7A-MAY',
            course: 'SPA',
            subject: 'SPA',
        }, {
            code: 'S3',
            ttCodes: ['S3'],
        })).toBe(true)
        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'S1-7A-MAY',
            course: 'S',
            subject: 'S',
        }, {
            code: 'F1',
            ttCodes: timetableCodes,
        })).toBe(false)
    })

    it('lists one-off TT groups above the generated timetable', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'INF', code: 'INF', name: 'Informatik', branch: 'alle', hours: 2 },
                { key: 'LPT', code: 'LPT', name: 'LPT', branch: 'alle', hours: 2, ttCodes: ['LET'] },
            ],
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', course: 'INF', subject: 'INF', weekday: 1, hour: 13, dates_count: 18 },
                { key: 'inf-fr', class_name: 'INF1-Grp2-KRO', course: 'INF', subject: 'INF', weekday: 5, hour: 12, dates_count: 18 },
                {
                    key: 'lpt-once',
                    class_name: 'LPT-1CK-DREI',
                    course: 'LET',
                    subject: 'LET',
                    weekday: 4,
                    hour: 10,
                    dates_count: 1,
                    dates: ['2026-09-30'],
                },
            ],
            schoolHours: [
                { hour: 10, from: '17:05:00', until: '17:50:00' },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables).toHaveLength(2)
        expect(Object.keys(ctx.generatedTimetables[0].slots).sort()).toEqual(['1-13'])
        expect(ctx.generatedTimetables[0].occasionalAppointments).toMatchObject([
            {
                code: 'LPT',
                name: 'LPT',
                dateTimeLabel: 'Mi, 30.09.2026 10. 17:05-17:50',
            },
        ])
        expect(ctx.generatedTimetables[0].slots['4-10']).toBeUndefined()
        expect(ctx.generatedTimetables[0].problems[0]).not.toContain('orange im Stundenplan markiert')
        expect(ctx.generatedTimetables[0].problems[0]).toContain('Mi, 30.09.2026 17:05-17:50')
    })

    it('shows one-off appointments inside the matching regular course item', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            subjectMappings: [],
            configuredCourseGroups: [
                {
                    key: 'd1-regular',
                    class_name: 'D1-7A-DREI',
                    course: 'D1',
                    subject: 'D',
                    weekday: 1,
                    hour: 12,
                    teacher: 'DREI',
                    room: '101',
                    dates_count: 18,
                },
                {
                    key: 'd1-regular-earlier',
                    class_name: 'D1-7A-DREI',
                    course: 'D1',
                    subject: 'D',
                    weekday: 1,
                    hour: 11,
                    teacher: 'DREI',
                    room: '101',
                    dates_count: 18,
                },
                {
                    key: 'd1-regular-di-14',
                    class_name: 'D1-7A-DREI',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 14,
                    teacher: 'DREI',
                    room: '101',
                    dates_count: 18,
                },
                {
                    key: 'd1-regular-di-15',
                    class_name: 'D1-7A-DREI',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 15,
                    teacher: 'DREI',
                    room: '101',
                    dates_count: 18,
                },
                {
                    key: 'd1-once',
                    class_name: 'D1-7A-DREI',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 2,
                    dates: ['2026-02-17'],
                    dates_count: 1,
                },
                {
                    key: 'd1-once-late',
                    class_name: 'D1-7A-DREI',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 3,
                    dates: ['2026-02-17'],
                    dates_count: 1,
                },
                {
                    key: 'd1-once-wed',
                    class_name: 'D1-7A-DREI',
                    course: 'D1',
                    subject: 'D',
                    weekday: 3,
                    hour: 10,
                    dates: ['2026-02-18'],
                    dates_count: 1,
                },
            ],
            weekdayOptions: [
                { shortTitle: 'Mo', value: 1 },
                { shortTitle: 'Di', value: 2 },
                { shortTitle: 'Mi', value: 3 },
            ],
            timeOptions: [
                { shortTitle: '2. 09:45-10:30', value: 2 },
                { shortTitle: '3. 10:40-11:25', value: 3 },
                { shortTitle: '11. 17:50-18:35', value: 11 },
                { shortTitle: '12. 18:45-19:30', value: 12 },
                { shortTitle: '14. 20:25-21:10', value: 14 },
                { shortTitle: '15. 21:10-21:55', value: 15 },
            ],
            schoolHours: [
                { hour: 2, from: '09:45:00', until: '10:30:00' },
                { hour: 3, from: '10:40:00', until: '11:25:00' },
                { hour: 10, from: '17:05:00', until: '17:50:00' },
                { hour: 11, from: '17:50:00', until: '18:35:00' },
                { hour: 12, from: '18:45:00', until: '19:30:00' },
                { hour: 14, from: '20:25:00', until: '21:10:00' },
                { hour: 15, from: '21:10:00', until: '21:55:00' },
            ],
        }
        const course = { key: 'D1', code: 'D1', ttCodes: ['D1'] }

        expect(methods.courseGroupItems.call(ctx, course)).toMatchObject([
            {
                title: 'D1-7A-DREI',
                hasOccasional: true,
                meta: 'Mo 11.-12., 17:50-19:30, Di 14.-15., 20:25-21:55\nEinzeltermine:\nDi, 17.02.2026 2.-3., 09:45-11:25\nMi, 18.02.2026 10. 17:05-17:50',
            },
        ])
        expect(methods.courseGroupDetailOccasionalMeta.call(ctx, ['Fr, 06.03.2026 8. 15:30-16:15'])).toBe(
            'Einzeltermine: Fr, 06.03.2026 8. 15:30-16:15',
        )
    })

    it('can select individual imported timetable course groups', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            deselectedCourseKeys: [],
            deselectedCourseGroupKeys: [],
            configuredCourseGroups: [],
            generatedTimetables: [{ key: 'generated-1' }],
            generationError: 'old',
            generationProblems: ['old'],
            saveLastRobotState() {
                this.saved = true
            },
        }
        const course = { key: 'D1', code: 'D1' }
        const group = { title: 'D1-1C-GOS' }

        expect(methods.courseGroupSelected.call(ctx, course, group)).toBe(true)

        methods.setCourseGroupSelected.call(ctx, course, group, false)

        expect(ctx.deselectedCourseGroupKeys).toEqual(['D1|D1-1C-GOS'])
        expect(methods.courseGroupSelected.call(ctx, course, group)).toBe(false)
        expect(ctx.generatedTimetables).toEqual([])
        expect(ctx.generationError).toBe('')
        expect(ctx.generationProblems).toEqual([])
        expect(ctx.saved).toBe(true)

        methods.setCourseGroupSelected.call(ctx, course, group, true)

        expect(ctx.deselectedCourseGroupKeys).toEqual([])
        expect(methods.courseGroupSelected.call(ctx, course, group)).toBe(true)
    })

    it('syncs course selection with imported timetable course groups', () => {
        const methods = (RobotTimetable as any).methods
        const courseGroups = [
            { title: 'D1-1C-GOS' },
            { title: 'D1-1C-HER' },
        ]
        const ctx = {
            ...methods,
            deselectedCourseKeys: [],
            deselectedCourseGroupKeys: [],
            generatedTimetables: [{ key: 'generated-1' }],
            generationError: 'old',
            generationProblems: ['old'],
            courseGroupItems() {
                return courseGroups
            },
            saveLastRobotState() {
                this.saved = true
            },
        }
        const course = { key: 'D1', code: 'D1' }

        expect(methods.courseSelected.call(ctx, course)).toBe(true)
        expect(methods.courseFullySelected.call(ctx, course)).toBe(true)

        methods.setCourseSelected.call(ctx, course, false)

        expect(ctx.deselectedCourseKeys).toEqual(['D1'])
        expect(ctx.deselectedCourseGroupKeys).toEqual(['D1|D1-1C-GOS', 'D1|D1-1C-HER'])
        expect(methods.courseSelected.call(ctx, course)).toBe(false)
        expect(methods.courseFullySelected.call(ctx, course)).toBe(false)
        expect(ctx.generatedTimetables).toEqual([])
        expect(ctx.generationError).toBe('')
        expect(ctx.generationProblems).toEqual([])
        expect(ctx.saved).toBe(true)

        methods.setCourseGroupSelected.call(ctx, course, courseGroups[0], true)

        expect(ctx.deselectedCourseKeys).toEqual([])
        expect(ctx.deselectedCourseGroupKeys).toEqual(['D1|D1-1C-HER'])
        expect(methods.courseSelected.call(ctx, course)).toBe(true)
        expect(methods.courseFullySelected.call(ctx, course)).toBe(false)
        expect(methods.coursePartiallySelected.call(ctx, course)).toBe(true)

        methods.setCourseGroupSelected.call(ctx, course, courseGroups[0], false)

        expect(ctx.deselectedCourseKeys).toEqual(['D1'])
        expect(ctx.deselectedCourseGroupKeys).toEqual(['D1|D1-1C-HER', 'D1|D1-1C-GOS'])
        expect(methods.courseSelected.call(ctx, course)).toBe(false)
    })

    it('does not select courses without imported timetable hours', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            deselectedCourseKeys: [],
            deselectedCourseGroupKeys: [],
            additionalCourseSelectedKeys: [],
            courseGroupItems() {
                return []
            },
            clearGeneratedTimetables() {
                this.cleared = true
            },
            saveLastRobotState() {
                this.saved = true
            },
        }
        const course = { key: 'Rk4', code: 'Rk4' }

        expect(methods.courseSelectable.call(ctx, course)).toBe(false)
        expect(methods.courseSelected.call(ctx, course)).toBe(false)
        expect(methods.courseFullySelected.call(ctx, course)).toBe(false)
        expect(methods.additionalCourseSelectable.call(ctx, course)).toBe(false)
        ctx.additionalCourseSelectedKeys = ['Rk4']
        expect(methods.additionalCourseSelected.call(ctx, course)).toBe(false)
        ctx.additionalCourseSelectedKeys = []

        methods.setCourseSelected.call(ctx, course, true)
        methods.setAdditionalCourseSelected.call(ctx, course, true)

        expect(ctx.deselectedCourseKeys).toEqual([])
        expect(ctx.additionalCourseSelectedKeys).toEqual([])
        expect(ctx.cleared).toBeUndefined()
        expect(ctx.saved).toBeUndefined()
    })

    it('ignores deselected imported timetable course groups during generation', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            deselectedCourseGroupKeys: ['D1|D1-1C-HER'],
            configuredCourseGroups: [
                {
                    class_name: 'D1-1C-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 1,
                    hour: 1,
                },
                {
                    class_name: 'D1-1C-HER',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 2,
                },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2],
            },
        }

        const candidateSets = methods.timetableCandidateSets.call(ctx, [
            { key: 'D1', code: 'D1', ttCodes: ['D1'], hours: 1 },
        ])

        expect(candidateSets[0].options.map(option => option.label)).toEqual(['D1-1C-GOS'])
        expect(candidateSets[0].matchingCourseGroupsCount).toBe(1)
    })

    it('keeps one-off overlap problems grouped while showing time ranges', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            schoolHours: [
                { hour: 14, from: '20:25:00', until: '21:10:00' },
                { hour: 15, from: '21:10:00', until: '21:55:00' },
            ],
        }

        const result = methods.timetableWithOccasionalSlots.call(ctx, {
            slots: {
                '2-14': { code: 'D1', name: 'Deutsch 1' },
                '2-15': { code: 'D1', name: 'Deutsch 1' },
            },
            problems: [],
        }, [
            {
                course: { code: 'LPT', name: 'LPT' },
                occasionalOptions: [
                    {
                        courseGroups: [
                            { weekday: 2, hour: 14, dates: ['2026-02-17'] },
                            { weekday: 2, hour: 15, dates: ['2026-02-17'] },
                        ],
                    },
                ],
            },
        ])

        expect(result.problems).toEqual([
            'LPT - LPT: Einzeltermin Di, 17.02.2026 20:25-21:10 <-> D1 - Deutsch 1 Di, 17.02.2026 20:25-21:10, Di, 17.02.2026 21:10-21:55 <-> D1 - Deutsch 1 Di, 17.02.2026 21:10-21:55 überschneidet sich mit D1 - Deutsch 1.',
        ])
        expect(result.occasionalAppointments).toMatchObject([
            {
                code: 'LPT',
                dateTimeLabel: 'Di, 17.02.2026 14. 20:25-21:10',
                conflictLabel: 'überschneidet sich mit D1 - Deutsch 1',
            },
            {
                code: 'LPT',
                dateTimeLabel: 'Di, 17.02.2026 15. 21:10-21:55',
                conflictLabel: 'überschneidet sich mit D1 - Deutsch 1',
            },
        ])
        expect(result.slots['2-14'].conflicts).toBeUndefined()
        expect(result.slots['2-15'].conflicts).toBeUndefined()
    })

    it('groups generated one-off appointments by course and timetable group', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }

        expect(methods.groupedOccasionalAppointments.call(ctx, {
            occasionalAppointments: [
                {
                    key: 'lpt-2',
                    courseKey: 'LPT',
                    code: 'LPT',
                    name: 'Lern- und Präsentationstechniken',
                    sourceLabel: 'LPT-1CK-DREI',
                    dateTimeLabel: 'Mi, 18.02.2026 10. Stunde',
                    date: '2026-02-18',
                    dateLabel: 'Mi, 18.02.2026',
                    weekday: 3,
                    hour: 10,
                    timeFrom: '',
                    timeUntil: '',
                    conflictLabel: '',
                    sortValue: '2026-02-18|03|10|LPT',
                },
                {
                    key: 'lpt-4',
                    courseKey: 'LPT',
                    code: 'LPT',
                    name: 'Lern- und Präsentationstechniken',
                    sourceLabel: 'LPT-1CK-DREI',
                    dateTimeLabel: 'Mi, 18.02.2026 11. Stunde',
                    date: '2026-02-18',
                    dateLabel: 'Mi, 18.02.2026',
                    weekday: 3,
                    hour: 11,
                    timeFrom: '',
                    timeUntil: '',
                    conflictLabel: '',
                    sortValue: '2026-02-18|03|11|LPT',
                },
                {
                    key: 'lpt-5',
                    courseKey: 'LPT',
                    code: 'LPT',
                    name: 'Lern- und Präsentationstechniken',
                    sourceLabel: 'LPT-1CK-DREI',
                    dateTimeLabel: 'Mi, 18.02.2026 12. Stunde',
                    date: '2026-02-18',
                    dateLabel: 'Mi, 18.02.2026',
                    weekday: 3,
                    hour: 12,
                    timeFrom: '',
                    timeUntil: '',
                    conflictLabel: '',
                    sortValue: '2026-02-18|03|12|LPT',
                },
                {
                    key: 'lpt-6',
                    courseKey: 'LPT',
                    code: 'LPT',
                    name: 'Lern- und Präsentationstechniken',
                    sourceLabel: 'LPT-1CK-DREI',
                    dateTimeLabel: 'Mi, 18.02.2026 13. Stunde',
                    date: '2026-02-18',
                    dateLabel: 'Mi, 18.02.2026',
                    weekday: 3,
                    hour: 13,
                    timeFrom: '',
                    timeUntil: '',
                    conflictLabel: '',
                    sortValue: '2026-02-18|03|13|LPT',
                },
                {
                    key: 'lpt-1',
                    courseKey: 'LPT',
                    code: 'LPT',
                    name: 'Lern- und Präsentationstechniken',
                    sourceLabel: 'LPT-1CK-DREI',
                    dateTimeLabel: 'Di, 17.02.2026 14. 20:25-21:10',
                    date: '2026-02-17',
                    dateLabel: 'Di, 17.02.2026',
                    weekday: 2,
                    hour: 14,
                    timeFrom: '20:25',
                    timeUntil: '21:10',
                    conflictLabel: 'überschneidet sich mit D1 - Deutsch 1',
                    sortValue: '2026-02-17|02|14|LPT',
                },
                {
                    key: 'lpt-3',
                    courseKey: 'LPT',
                    code: 'LPT',
                    name: 'Lern- und Präsentationstechniken',
                    sourceLabel: 'LPT-1CK-DREI',
                    dateTimeLabel: 'Di, 17.02.2026 15. 21:10-21:55',
                    date: '2026-02-17',
                    dateLabel: 'Di, 17.02.2026',
                    weekday: 2,
                    hour: 15,
                    timeFrom: '21:10',
                    timeUntil: '21:55',
                    conflictLabel: 'überschneidet sich mit D1 - Deutsch 1',
                    sortValue: '2026-02-17|02|15|LPT',
                },
            ],
        })).toMatchObject([
            {
                courseKey: 'LPT',
                title: 'LPT Lern- und Präsentationstechniken - 1CK-DREI',
                rows: [
                    {
                        dateTimeLabel: 'Di, 17.02.2026 14.-15., 20:25-21:55',
                        hasConflict: true,
                        metaLabel: 'überschneidet sich mit D1 - Deutsch 1',
                    },
                    {
                        dateTimeLabel: 'Mi, 18.02.2026 10.-13. Stunde',
                        hasConflict: false,
                        metaLabel: '',
                    },
                ],
            },
        ])
    })

    it('selects only one one-off appointment alternative per course', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }
        const timetable = {
            selectedOccasionalAppointmentGroups: {},
            occasionalAppointments: [
                {
                    key: 'lpt-1',
                    courseKey: 'LPT',
                    code: 'LPT',
                    name: 'Lern- und Präsentationstechniken',
                    sourceLabel: 'LPT-1CK-DREI',
                    dateTimeLabel: 'Di, 17.02.2026 14. 20:25-21:10',
                    date: '2026-02-17',
                    dateLabel: 'Di, 17.02.2026',
                    weekday: 2,
                    hour: 14,
                    timeFrom: '20:25',
                    timeUntil: '21:10',
                    sortValue: '2026-02-17|02|14|LPT',
                },
                {
                    key: 'lpt-2',
                    courseKey: 'LPT',
                    code: 'LPT',
                    name: 'Lern- und Präsentationstechniken',
                    sourceLabel: 'LPT-1R-ENNS',
                    dateTimeLabel: 'Di, 17.02.2026 14. 20:25-21:10',
                    date: '2026-02-17',
                    dateLabel: 'Di, 17.02.2026',
                    weekday: 2,
                    hour: 14,
                    timeFrom: '20:25',
                    timeUntil: '21:10',
                    sortValue: '2026-02-17|02|14|LPT',
                },
                {
                    key: 'eth-1',
                    courseKey: 'ETH1',
                    code: 'ETH1',
                    name: 'Ethik 1',
                    sourceLabel: 'ETH1-1RU-PLÖC',
                    dateTimeLabel: 'Fr, 06.03.2026 8. 15:30-16:15',
                    date: '2026-03-06',
                    dateLabel: 'Fr, 06.03.2026',
                    weekday: 5,
                    hour: 8,
                    timeFrom: '15:30',
                    timeUntil: '16:15',
                    sortValue: '2026-03-06|05|08|ETH1',
                },
            ],
        }
        const groups = methods.groupedOccasionalAppointments.call(ctx, timetable)
        const firstLptGroup = groups.find(group => group.sourceLabel === 'LPT-1CK-DREI')
        const secondLptGroup = groups.find(group => group.sourceLabel === 'LPT-1R-ENNS')
        const ethGroup = groups.find(group => group.sourceLabel === 'ETH1-1RU-PLÖC')

        expect(methods.occasionalAppointmentGroupHasAlternatives.call(ctx, timetable, firstLptGroup)).toBe(true)
        expect(methods.occasionalAppointmentGroupHasAlternatives.call(ctx, timetable, ethGroup)).toBe(false)

        methods.setOccasionalAppointmentGroupSelected.call(ctx, timetable, firstLptGroup, true)
        expect(methods.occasionalAppointmentGroupSelected.call(ctx, timetable, firstLptGroup)).toBe(true)
        expect(methods.generatedCellOccasionalMarkers.call(ctx, timetable, 2, 14)).toEqual([
            { key: 'lpt-1', code: 'LPT' },
        ])

        methods.setOccasionalAppointmentGroupSelected.call(ctx, timetable, secondLptGroup, true)
        expect(methods.occasionalAppointmentGroupSelected.call(ctx, timetable, firstLptGroup)).toBe(false)
        expect(methods.occasionalAppointmentGroupSelected.call(ctx, timetable, secondLptGroup)).toBe(true)
        expect(methods.generatedCellOccasionalMarkers.call(ctx, timetable, 2, 14)).toEqual([
            { key: 'lpt-2', code: 'LPT' },
        ])

        methods.setOccasionalAppointmentGroupSelected.call(ctx, timetable, secondLptGroup, false)
        expect(timetable.selectedOccasionalAppointmentGroups).toEqual({})
        expect(methods.generatedCellOccasionalMarkers.call(ctx, timetable, 2, 14)).toEqual([])
    })

    it('makes one-off appointments selectable when they match a scheduled course group', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }
        const timetable = {
            selectedOccasionalAppointmentGroups: {},
            slots: {
                '5-8': {
                    key: 'ETH1',
                    code: 'ETH1',
                    name: 'Ethik 1',
                    sourceLabel: 'ETH1-1RU-PLÖC',
                },
            },
            occasionalAppointments: [
                {
                    key: 'eth-1',
                    courseKey: 'ETH1',
                    code: 'ETH1',
                    name: 'Ethik 1',
                    sourceLabel: 'ETH1-1RU-PLÖC',
                    dateTimeLabel: 'Fr, 06.03.2026 8. 15:30-16:15',
                    date: '2026-03-06',
                    dateLabel: 'Fr, 06.03.2026',
                    weekday: 5,
                    hour: 8,
                    timeFrom: '15:30',
                    timeUntil: '16:15',
                    sortValue: '2026-03-06|05|08|ETH1',
                },
            ],
        }
        const ethGroup = methods.groupedOccasionalAppointments.call(ctx, timetable)[0]

        expect(methods.occasionalAppointmentGroupHasAlternatives.call(ctx, timetable, ethGroup)).toBe(false)
        expect(methods.occasionalAppointmentGroupSelectable.call(ctx, timetable, ethGroup)).toBe(true)
        expect(methods.defaultOccasionalAppointmentGroupSelections.call(ctx, timetable)).toEqual({
            ETH1: ethGroup.key,
        })

        methods.setOccasionalAppointmentGroupSelected.call(ctx, timetable, ethGroup, true)

        expect(methods.generatedCellOccasionalMarkers.call(ctx, timetable, 5, 8)).toEqual([
            { key: 'eth-1', code: 'ETH1' },
        ])
    })

    it('does not overlay a non-conflicting one-off appointment on an occupied timetable cell', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }
        const timetable = {
            selectedOccasionalAppointmentGroups: {},
            slots: {
                '3-14': {
                    key: 'E7',
                    code: 'E7',
                    name: 'Englisch 7',
                    sourceLabel: 'E7-4Q-RIE',
                    courseGroup: {
                        dates: ['2026-05-13', '2026-05-20'],
                    },
                },
            },
            occasionalAppointments: [
                {
                    key: 'm7-1',
                    courseKey: 'M7',
                    code: 'M7',
                    name: 'Mathematik 7',
                    sourceLabel: 'M7-4Q-MAL',
                    dateTimeLabel: 'Mi, 24.06.2026 14. 20:25-21:10',
                    date: '2026-06-24',
                    weekday: 3,
                    hour: 14,
                    sortValue: '2026-06-24|03|14|M7',
                },
            ],
        }
        const group = methods.groupedOccasionalAppointments.call(ctx, timetable)[0]

        methods.setOccasionalAppointmentGroupSelected.call(ctx, timetable, group, true)

        expect(methods.generatedCellOccasionalMarkers.call(ctx, timetable, 3, 14)).toEqual([])
    })

    it('overlays a conflicting one-off appointment on an occupied timetable cell', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }
        const timetable = {
            selectedOccasionalAppointmentGroups: {},
            slots: {
                '3-14': {
                    key: 'E7',
                    code: 'E7',
                    name: 'Englisch 7',
                    sourceLabel: 'E7-4Q-RIE',
                    courseGroup: {
                        dates: ['2026-05-13', '2026-05-20'],
                    },
                },
            },
            occasionalAppointments: [
                {
                    key: 'm7-1',
                    courseKey: 'M7',
                    code: 'M7',
                    name: 'Mathematik 7',
                    sourceLabel: 'M7-4Q-MAL',
                    dateTimeLabel: 'Mi, 13.05.2026 14. 20:25-21:10',
                    date: '2026-05-13',
                    weekday: 3,
                    hour: 14,
                    sortValue: '2026-05-13|03|14|M7',
                },
            ],
        }
        const group = methods.groupedOccasionalAppointments.call(ctx, timetable)[0]

        methods.setOccasionalAppointmentGroupSelected.call(ctx, timetable, group, true)

        expect(methods.generatedCellOccasionalMarkers.call(ctx, timetable, 3, 14)).toEqual([
            { key: 'm7-1', code: 'M7' },
        ])
    })

    it('hides one-off appointment problems already shown in the appointments card', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }

        expect(methods.displayedTimetableProblems.call(ctx, {
            problems: [
                'LPT - Lern- und Präsentationstechniken: nur Einzeltermine (Di, 17.02.2026 20:25-21:10).',
                'LPT - Lern- und Präsentationstechniken: Einzeltermin Di, 17.02.2026 20:25-21:10 <-> D1 - Deutsch 1 Di, 17.02.2026 20:25-21:10 überschneidet sich mit D1 - Deutsch 1.',
                'D1 - Deutsch 1: D1-1K-GOS (Di 14.-15., 20:25-21:55) ist in deinen Zeitvorgaben nicht erlaubt.',
            ],
        })).toEqual([
            'D1 - Deutsch 1: D1-1K-GOS (Di 14.-15., 20:25-21:55) ist in deinen Zeitvorgaben nicht erlaubt.',
        ])
    })

    it('shows one-off appointments only for the timetable alternative that was used', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            schoolHours: [
                { hour: 14, from: '20:25:00', until: '21:10:00' },
            ],
        }

        const result = methods.timetableWithOccasionalSlots.call(ctx, {
            slots: {
                '2-14': {
                    key: 'D1',
                    code: 'D1',
                    name: 'Deutsch 1',
                    sourceLabel: 'D1-1K-GOS',
                    courseGroup: { weekday: 2, hour: 14, dates_count: 18 },
                },
            },
            problems: [],
        }, [
            {
                course: { key: 'D1', code: 'D1', name: 'Deutsch 1' },
                hasOnlyOccasionalMatches: false,
                occasionalOptions: [
                    {
                        label: 'D1-1K-GOS',
                        courseGroups: [
                            { key: 'used-once', class_name: 'D1-1K-GOS', weekday: 2, hour: 14, dates: ['2026-02-17'], dates_count: 1 },
                        ],
                    },
                    {
                        label: 'D1-1C-HER',
                        courseGroups: [
                            { key: 'unused-once', class_name: 'D1-1C-HER', weekday: 2, hour: 14, dates: ['2026-02-24'], dates_count: 1 },
                        ],
                    },
                ],
            },
        ])

        expect(result.occasionalAppointments.map(appointment => appointment.sourceLabel)).toEqual(['D1-1K-GOS'])
        expect(result.occasionalAppointments.map(appointment => appointment.key).join('|')).not.toContain('unused-once')
    })

    it('lists pure one-off course alternatives in one generated timetable', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'LPT', code: 'LPT', name: 'Lern- und Präsentationstechniken', branch: 'alle', hours: 2 },
            ],
            configuredCourseGroups: [
                { key: 'lpt-1ck', class_name: 'LPT-1CK-DREI', course: 'LPT', subject: 'LPT', weekday: 2, hour: 14, dates: ['2026-02-17'], dates_count: 1 },
                { key: 'lpt-1r', class_name: 'LPT-1R-ENNS', course: 'LPT', subject: 'LPT', weekday: 2, hour: 14, dates: ['2026-02-17'], dates_count: 1 },
                { key: 'lpt-1u', class_name: 'LPT-1U-HER', course: 'LPT', subject: 'LPT', weekday: 5, hour: 7, dates: ['2026-02-20'], dates_count: 1 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
            weekdayOptions: [
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
                { title: 'Freitag', shortTitle: 'Fr', value: 5 },
            ],
            timeOptions: [
                { title: '7. Stunde', shortTitle: '7. 14:45-15:30', value: 7 },
                { title: '14. Stunde', shortTitle: '14. 20:25-21:10', value: 14 },
            ],
            schoolHours: [
                { hour: 7, from: '14:45:00', until: '15:30:00' },
                { hour: 14, from: '20:25:00', until: '21:10:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generatedTimetables).toHaveLength(1)
        expect(ctx.generatedTimetables[0].occasionalAppointments.map(appointment => appointment.sourceLabel)).toEqual([
            'LPT-1CK-DREI',
            'LPT-1R-ENNS',
            'LPT-1U-HER',
        ])
        expect(computed.generatedTimes.call(ctx).map(time => time.value)).toEqual([7, 8, 9, 10, 11, 12, 13, 14])
    })

    it('keeps pure one-off alternatives together when regular courses are scheduled', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 1 },
                { key: 'LPT', code: 'LPT', name: 'Lern- und Präsentationstechniken', branch: 'alle', hours: 2 },
            ],
            configuredCourseGroups: [
                { key: 'd1', class_name: 'D1-1K-GOS', course: 'D1', subject: 'D', weekday: 2, hour: 14, dates_count: 18 },
                { key: 'lpt-1ck', class_name: 'LPT-1CK-DREI', course: 'LPT', subject: 'LPT', weekday: 2, hour: 14, dates: ['2026-02-17'], dates_count: 1 },
                { key: 'lpt-1r', class_name: 'LPT-1R-ENNS', course: 'LPT', subject: 'LPT', weekday: 2, hour: 14, dates: ['2026-02-17'], dates_count: 1 },
                { key: 'lpt-1u', class_name: 'LPT-1U-HER', course: 'LPT', subject: 'LPT', weekday: 5, hour: 7, dates: ['2026-02-20'], dates_count: 1 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
            weekdayOptions: [
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
                { title: 'Freitag', shortTitle: 'Fr', value: 5 },
            ],
            timeOptions: [
                { title: '7. Stunde', shortTitle: '7. 14:45-15:30', value: 7 },
                { title: '14. Stunde', shortTitle: '14. 20:25-21:10', value: 14 },
            ],
            schoolHours: [
                { hour: 7, from: '14:45:00', until: '15:30:00' },
                { hour: 14, from: '20:25:00', until: '21:10:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generatedTimetables).toHaveLength(1)
        expect(ctx.generatedTimetables[0].occasionalAppointments.map(appointment => appointment.sourceLabel)).toEqual([
            'LPT-1CK-DREI',
            'LPT-1R-ENNS',
            'LPT-1U-HER',
        ])
        expect(ctx.generatedTimetables[0].slots['2-14'].code).toBe('D1')

        const selectedGroup = methods.groupedOccasionalAppointments.call(ctx, ctx.generatedTimetables[0])
            .find(group => group.sourceLabel === 'LPT-1U-HER')

        expect(ctx.generatedTimetables[0].selectedOccasionalAppointmentGroups).toEqual({
            LPT: selectedGroup?.key,
        })
        expect(methods.generatedCellOccasionalMarkers.call(ctx, ctx.generatedTimetables[0], 5, 7)).toMatchObject([
            { code: 'LPT' },
        ])
    })

    it('does not let one-off appointments block regular timetable courses', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }
        const regularOption = {
            courseGroups: [
                { weekday: 2, hour: 14, dates_count: 18 },
            ],
        }
        const assignedSlots = {
            '2-14': {
                code: 'LPT',
                courseGroup: { weekday: 2, hour: 14, dates: ['2026-02-17'], dates_count: 1 },
            },
        }

        expect(methods.optionHasBlockingUsedSlot.call(ctx, regularOption, assignedSlots, new Set(['2-14']))).toBe(false)
    })

    it('keeps regular timetable cells visible when one-off appointments overlap them', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 1 },
                { key: 'LPT', code: 'LPT', name: 'LPT', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                { key: 'd1-regular', class_name: 'D1-1K-GOS', course: 'D1', subject: 'D', weekday: 2, hour: 14, dates_count: 18 },
                { key: 'lpt-once', class_name: 'LPT-1CK-DREI', course: 'LPT', subject: 'LPT', weekday: 2, hour: 14, dates: ['2026-02-17'], dates_count: 1 },
            ],
            constraints: {
                availableWeekdays: [2],
                excludedWeekdayTimes: [],
                availableTimes: [14],
            },
            weekdayOptions: [
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
            ],
            timeOptions: [
                { title: '14. Stunde', shortTitle: '14. 20:25-21:10', value: 14 },
            ],
            schoolHours: [
                { hour: 14, from: '20:25:00', until: '21:10:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generatedTimetables[0].slots['2-14'].code).toBe('D1')
        expect(ctx.generatedTimetables[0].slots['2-14'].sourceLabel).toBe('D1-1K-GOS')
        expect(ctx.generatedTimetables[0].occasionalAppointments).toMatchObject([
            {
                code: 'LPT',
                dateTimeLabel: 'Di, 17.02.2026 14. 20:25-21:10',
                conflictLabel: 'überschneidet sich mit D1 - Deutsch 1',
            },
        ])
    })

    it('schedules a selected course group even when it has fewer periods than the subject hours', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 3 },
            ],
            configuredCourseGroups: [
                { key: 'd1-tu-14', class_name: 'D1-1K-GOS', course: 'D', subject: 'D', weekday: 2, hour: 14, dates_count: 20 },
                { key: 'd1-tu-15', class_name: 'D1-1K-GOS', course: 'D', subject: 'D', weekday: 2, hour: 15, dates_count: 10 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
            weekdayOptions: [
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
            ],
            timeOptions: [
                { title: '14. Stunde', shortTitle: '14. 20:25-21:10', value: 14 },
                { title: '15. Stunde', shortTitle: '15. 21:10-21:55', value: 15 },
            ],
            schoolHours: [
                { hour: 14, from: '20:25:00', until: '21:10:00' },
                { hour: 15, from: '21:10:00', until: '21:55:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables[0].problems).toEqual([])
        expect(ctx.generatedTimetables[0].slots['2-14'].sourceLabel).toBe('D1-1K-GOS')
        expect(ctx.generatedTimetables[0].slots['2-15'].sourceLabel).toBe('D1-1K-GOS')
    })

    it('sorts generated slot conflicts by date', () => {
        const methods = (RobotTimetable as any).methods

        expect(methods.generatedSlotConflicts.call({}, {
            conflicts: [
                { label: 'GW1 - Geografie 1 Fr, 13.03.2026 14:45-15:30', sortValue: '2026-03-13-7-GW1' },
                { label: 'LPT - LPT Fr, 20.02.2026 14:45-15:30', sortValue: '2026-02-20-7-LPT' },
            ],
        })).toEqual([
            'LPT - LPT Fr, 20.02.2026 14:45-15:30',
            'GW1 - Geografie 1 Fr, 13.03.2026 14:45-15:30',
        ])
    })

    it('renders conflict cells with only the other conflicting course blocks', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedRobotTimetable: {
                slots: {
                    '5-13': {
                        key: 'INF3',
                        code: 'INF3',
                        name: 'Informatik 3',
                        sourceLabel: 'INF3-8AB-MAY',
                        courseGroup: { class_name: 'INF3-8AB-MAY', weekday: 5, hour: 13 },
                        conflicts: [
                            {
                                label: 'ÖKO2 - Ökonomie 19:30-20:15',
                                sortValue: '13-ÖKO2',
                                code: 'ÖKO2',
                                name: 'Ökonomie',
                                sourceLabel: 'ÖKO2- BIE',
                                courseGroup: { class_name: 'ÖKO2- BIE', weekday: 5, hour: 13 },
                            },
                        ],
                    },
                },
            },
        }
        const blocks = methods.generatedSlotConflictBlocks.call(ctx, ctx.selectedRobotTimetable.slots['5-13'])

        expect(blocks.map(block => block.code)).toEqual(['ÖKO2'])
        expect(blocks.map(block => methods.generatedSlotDetails.call(ctx, block))).toEqual([
            'ÖKO2- BIE',
        ])
        expect(methods.courseOverlapsInSelectedTimetable.call(ctx, { key: 'INF3', code: 'INF3' })).toBe(true)
        expect(methods.courseOverlapsInSelectedTimetable.call(ctx, { key: 'ÖKO2', code: 'ÖKO2' })).toBe(true)
        expect(methods.courseOverlapsInSelectedTimetable.call(ctx, { key: 'D1', code: 'D1' })).toBe(false)
        expect(methods.courseUsedInSelectedTimetable.call(ctx, { key: 'INF3', code: 'INF3' })).toBe(true)
        expect(methods.courseUsedInSelectedTimetable.call(ctx, { key: 'ÖKO2', code: 'ÖKO2' })).toBe(false)
    })

    it('does not duplicate the generated slot when it also appears in its conflict list', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = { ...methods }
        const slot = {
            key: 'GW1',
            code: 'GW1',
            name: 'Geografie 1',
            sourceLabel: 'GWB1-1C-HÖF',
            courseGroup: { course: 'GW1', class_name: 'GWB1-1C-HÖF', weekday: 5, hour: 7 },
            conflicts: [
                {
                    label: 'LPT - LPT 14:45-15:30',
                    sortValue: '01-LPT',
                    code: 'LPT',
                    name: 'LPT',
                    sourceLabel: 'LPT-1CK-DREI',
                    courseGroup: { course: 'LPT', class_name: 'LPT-1CK-DREI', weekday: 5, hour: 7 },
                },
                {
                    label: 'GW1 - Geografie 1 14:45-15:30',
                    sortValue: '02-GW1',
                    code: 'GW1',
                    name: 'Geografie 1',
                    sourceLabel: 'GWB1-1C-HÖF',
                    courseGroup: { course: 'GW1', class_name: 'GWB1-1C-HÖF', weekday: 5, hour: 7 },
                },
            ],
        }

        const blocks = methods.generatedSlotConflictBlocks.call(ctx, slot)

        expect(blocks.map(block => `${block.code}|${block.sourceLabel}`)).toEqual(['LPT|LPT-1CK-DREI'])
    })

    it('shows one-off conflict courses as cell markers instead of full conflict blocks', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            constraints: {
                availableWeekdays: [2],
                availableTimes: [14],
                excludedWeekdayTimes: [],
            },
            selectedRobotTimetable: {
                slots: {
                    '2-14': {
                        key: 'GW1',
                        code: 'GW1',
                        name: 'Geografie 1',
                        sourceLabel: 'GWB1-1C-HÖF',
                        courseGroup: { course: 'GW1', class_name: 'GWB1-1C-HÖF', weekday: 2, hour: 14 },
                        conflicts: [
                            {
                                label: 'LPT - LPT Di, 17.02.2026 20:25-21:10',
                                sortValue: '2026-02-17-14-LPT',
                                code: 'LPT',
                                name: 'LPT',
                                sourceLabel: 'LPT-1CK-DREI',
                                courseGroup: {
                                    course: 'LPT',
                                    class_name: 'LPT-1CK-DREI',
                                    weekday: 2,
                                    hour: 14,
                                    dates: ['2026-02-17'],
                                },
                            },
                        ],
                    },
                },
                occasionalAppointments: [],
            },
        }

        const slot = ctx.selectedRobotTimetable.slots['2-14']

        expect(methods.generatedSlotConflictBlocks.call(ctx, slot)).toEqual([])
        expect(methods.robotTimetableOccasionalMarkers.call(ctx, 2, 14)).toMatchObject([
            {
                code: 'LPT',
                sourceLabel: 'LPT-1CK-DREI',
            },
        ])
        expect(methods.robotTimetableCellClasses.call(ctx, 2, 14)).toMatchObject({
            'robot-generated-cell--conflict': false,
            'robot-generated-cell--has-occasional': true,
        })
        expect(methods.displayedOccasionalAppointmentGroups.call(ctx, ctx.selectedRobotTimetable)).toMatchObject([
            {
                code: 'LPT',
                sourceLabel: 'LPT-1CK-DREI',
                rows: [
                    {
                        dateTimeLabel: 'Di, 17.02.2026',
                        hasConflict: true,
                        metaLabel: 'überschneidet sich mit GW1 - Geografie 1',
                    },
                ],
            },
        ])
    })

    it('shows the regular conflict course when a one-off course is stored as the slot', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            constraints: {
                availableWeekdays: [3],
                availableTimes: [10],
                excludedWeekdayTimes: [],
            },
            selectedRobotTimetable: {
                slots: {
                    '3-10': {
                        key: 'LPT',
                        code: 'LPT',
                        name: 'LPT',
                        sourceLabel: 'LPT-1CK-DREI',
                        isOccasional: true,
                        courseGroup: {
                            course: 'LPT',
                            class_name: 'LPT-1CK-DREI',
                            weekday: 3,
                            hour: 10,
                            dates: ['2026-02-18'],
                        },
                        conflicts: [
                            {
                                label: 'M1 - Mathematik 1 17:05-17:50',
                                sortValue: '10-M1',
                                code: 'M1',
                                name: 'Mathematik 1',
                                sourceLabel: 'M1-1C-MAY',
                                dateRangeLabel: '18.02.-8.7.',
                                courseGroup: {
                                    course: 'M1',
                                    class_name: 'M1-1C-MAY',
                                    weekday: 3,
                                    hour: 10,
                                },
                            },
                        ],
                    },
                },
                occasionalAppointments: [],
            },
        }

        expect(methods.robotTimetableDisplaySlot.call(ctx, 3, 10)).toMatchObject({
            code: 'M1',
            sourceLabel: 'M1-1C-MAY',
        })
        expect(methods.generatedSlotTitle.call(ctx, methods.robotTimetableDisplaySlot.call(ctx, 3, 10))).toBe('M1')
        expect(methods.generatedSlotDateLabel.call(ctx, methods.robotTimetableDisplaySlot.call(ctx, 3, 10))).toBe('')
        expect(methods.robotTimetableOccasionalMarkers.call(ctx, 3, 10)).toMatchObject([
            {
                code: 'LPT',
                sourceLabel: 'LPT-1CK-DREI',
            },
        ])
        expect(methods.robotTimetableCellClasses.call(ctx, 3, 10)).toMatchObject({
            'robot-generated-cell--filled': true,
            'robot-generated-cell--conflict': false,
            'robot-generated-cell--has-occasional': true,
        })
        expect(methods.courseOverlapsInSelectedTimetable.call(ctx, { key: 'M1', code: 'M1' })).toBe(false)
        expect(methods.courseOverlapsInSelectedTimetable.call(ctx, { key: 'LPT', code: 'LPT' })).toBe(false)
        expect(methods.displayedOccasionalAppointmentGroups.call(ctx, ctx.selectedRobotTimetable)).toMatchObject([
            {
                code: 'LPT',
                sourceLabel: 'LPT-1CK-DREI',
                rows: [
                    {
                        dateTimeLabel: 'Mi, 18.02.2026',
                        hasConflict: true,
                        metaLabel: 'überschneidet sich mit M1 - Mathematik 1',
                    },
                ],
            },
        ])
    })

    it('renders same-slot entries as visual overlaps without backend conflict labels', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedRobotTimetable: {
                slots: {
                    '5-7': {
                        key: 'S4',
                        code: 'S4',
                        name: 'Spanisch 4',
                        sourceLabel: 'SPA4-KOR',
                        dateRangeLabel: '20.02.',
                        courseGroup: {
                            key: 'spa4',
                            class_name: 'SPA4-KOR',
                            weekday: 5,
                            hour: 7,
                            dates: ['2026-02-20'],
                            dates_count: 4,
                        },
                        sameSlotEntries: [
                            {
                                key: 'S5',
                                code: 'S5',
                                name: 'Spanisch 5',
                                sourceLabel: 'SPA5-PIB',
                                dateRangeLabel: '8.5.',
                                courseGroup: {
                                    key: 'spa5',
                                    class_name: 'SPA5-PIB',
                                    weekday: 5,
                                    hour: 7,
                                    dates: ['2026-05-08'],
                                    dates_count: 4,
                                },
                            },
                        ],
                    },
                },
            },
        }

        const sameSlotBlocks = methods.generatedSlotSameSlotBlocks.call(ctx, ctx.selectedRobotTimetable.slots['5-7'])

        expect(methods.generatedSlotConflicts.call(ctx, ctx.selectedRobotTimetable.slots['5-7'])).toEqual([])
        expect(methods.robotTimetableCellClasses.call(ctx, 5, 7)['robot-generated-cell--conflict']).toBe(false)
        expect(methods.generatedSlotTitle.call(ctx, ctx.selectedRobotTimetable.slots['5-7'])).toBe('SPA4-KOR / S4')
        expect(methods.generatedSlotDateLabel.call(ctx, ctx.selectedRobotTimetable.slots['5-7'])).toBe('20.02.')
        expect(methods.generatedSlotTitle.call(ctx, sameSlotBlocks[0])).toBe('SPA5-PIB / S5')
        expect(methods.generatedSlotDateLabel.call(ctx, sameSlotBlocks[0])).toBe('8.5.')
        expect(sameSlotBlocks.map(block => block.code)).toEqual(['S5'])
        expect(methods.selectedRobotTimetableCourseGroups.call(ctx).map(courseGroup => courseGroup.key)).toEqual([
            'spa4',
            'spa5',
        ])
        expect(methods.courseOverlapsInSelectedTimetable.call(ctx, { key: 'S4', code: 'S4' })).toBe(false)
        expect(methods.courseOverlapsInSelectedTimetable.call(ctx, { key: 'S5', code: 'S5' })).toBe(false)
    })

    it('hides generated date ranges for plain single-course cells', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = { ...methods }
        const slot = {
            key: 'D2',
            code: 'D2',
            name: 'Deutsch 2',
            sourceLabel: 'D2-1U-HER',
            dateRangeLabel: '21.02.-18.4.',
            courseGroup: {
                key: 'd2',
                class_name: 'D2-1U-HER',
                weekday: 6,
                hour: 4,
                dates: ['2026-02-21', '2026-03-07', '2026-03-21', '2026-04-18'],
            },
        }

        expect(methods.generatedSlotTitle.call(ctx, slot)).toBe('D2')
        expect(methods.generatedSlotDateLabel.call(ctx, slot)).toBe('')
        expect(methods.generatedSlotDetails.call(ctx, slot)).toBe('D2-1U-HER')
    })

    it('does not show a regular date range when only an Einzeltermin shares the cell', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = { ...methods }
        const slot = {
            key: 'M1',
            code: 'M1',
            name: 'Mathematik 1',
            sourceLabel: 'M1-1C-MAY',
            dateRangeLabel: '18.02.-8.7.',
            courseGroup: {
                key: 'm1',
                class_name: 'M1-1C-MAY',
                weekday: 3,
                hour: 10,
                dates: ['2026-02-18', '2026-07-08'],
                dates_count: 20,
            },
            sameSlotEntries: [
                {
                    key: 'lpt',
                    code: 'LPT',
                    name: 'LPT',
                    sourceLabel: 'LPT-1CK-DREI',
                    dateRangeLabel: '18.02.',
                    isOccasional: true,
                    courseGroup: {
                        key: 'lpt',
                        class_name: 'LPT-1CK-DREI',
                        weekday: 3,
                        hour: 10,
                        dates: ['2026-02-18'],
                        dates_count: 1,
                    },
                },
            ],
        }

        expect(methods.generatedSlotTitle.call(ctx, slot)).toBe('M1')
        expect(methods.generatedSlotDateLabel.call(ctx, slot)).toBe('')
        expect(methods.generatedSlotDetails.call(ctx, slot)).toBe('M1-1C-MAY')
    })

    it('keeps date-different same-slot courses in locally generated timetables', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'S4', code: 'S4', name: 'Spanisch 4', branch: 'alle', hours: 1 },
                { key: 'S5', code: 'S5', name: 'Spanisch 5', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                {
                    key: 'spa4',
                    class_name: 'SPA4-KOR',
                    course: 'S4',
                    subject: 'S4',
                    weekday: 5,
                    hour: 7,
                    dates: ['2026-02-20'],
                    dates_count: 20,
                },
                {
                    key: 'spa5',
                    class_name: 'SPA5-PIB',
                    course: 'S5',
                    subject: 'S5',
                    weekday: 5,
                    hour: 7,
                    dates: ['2026-05-08'],
                    dates_count: 20,
                },
            ],
            constraints: {
                availableWeekdays: [5],
                excludedWeekdayTimes: [],
                availableTimes: [7],
            },
            weekdayOptions: [
                { title: 'Freitag', shortTitle: 'Fr', value: 5 },
            ],
            timeOptions: [
                { title: '7. Stunde', shortTitle: '7.', value: 7 },
            ],
            schoolHours: [
                { hour: 7, from: '14:45:00', until: '15:30:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables[0].problems).toEqual([])
        expect(methods.scheduledCourseCountForSlots.call(ctx, ctx.generatedTimetables[0].slots)).toBe(2)
        expect(ctx.generatedTimetables[0].slots['5-7'].code).toBe('S4')
        expect(ctx.generatedTimetables[0].slots['5-7'].sameSlotEntries.map(entry => entry.code)).toEqual(['S5'])
        expect(ctx.generatedTimetables[0].slots['5-7'].dateRangeLabel).toBe('20.02.')
        expect(ctx.generatedTimetables[0].slots['5-7'].sameSlotEntries[0].dateRangeLabel).toBe('8.5.')
        ctx.selectedRobotTimetable = ctx.generatedTimetables[0]
        expect(methods.robotTimetableCellClasses.call(ctx, 5, 7)['robot-generated-cell--conflict']).toBe(false)
    })

    it('marks exact course group details as used or no longer fitting in the selected timetable', () => {
        const methods = (RobotTimetable as any).methods
        const course = { key: 'D1', code: 'D1', ttCodes: ['D1'], name: 'Deutsch 1', hours: 2 }
        const usedGroup = {
            key: 'D1|D1-1C-GOS',
            title: 'D1-1C-GOS',
            courseGroups: [
                { key: 'd1-gos-1', class_name: 'D1-1C-GOS', course: 'D1', subject: 'D', weekday: 1, hour: 11 },
            ],
        }
        const conflictingGroup = {
            key: 'D1|D1-1C-HER',
            title: 'D1-1C-HER',
            courseGroups: [
                { key: 'd1-her-1', class_name: 'D1-1C-HER', course: 'D1', subject: 'D', weekday: 1, hour: 11 },
            ],
        }
        const freeGroup = {
            key: 'D1|D1-1K-GOS',
            title: 'D1-1K-GOS',
            courseGroups: [
                { key: 'd1-1k-1', class_name: 'D1-1K-GOS', course: 'D1', subject: 'D', weekday: 2, hour: 14 },
            ],
        }
        const ctx = {
            ...methods,
            selectedRobotTimetable: {
                slots: {
                    '1-11': {
                        key: 'D1',
                        code: 'D1',
                        sourceLabel: 'D1-1C-GOS',
                        courseGroup: usedGroup.courseGroups[0],
                    },
                },
            },
        }

        expect(methods.courseGroupUsedInSelectedTimetable.call(ctx, course, usedGroup)).toBe(true)
        expect(methods.courseGroupNoLongerFitsSelectedTimetable.call(ctx, course, usedGroup)).toBe(false)
        expect(methods.courseGroupUsedInSelectedTimetable.call(ctx, course, conflictingGroup)).toBe(false)
        expect(methods.courseGroupNoLongerFitsSelectedTimetable.call(ctx, course, conflictingGroup)).toBe(true)
        expect(methods.courseGroupNoLongerFitsSelectedTimetable.call(ctx, course, freeGroup)).toBe(false)
    })

    it('marks a course red when all visible detail groups no longer fit', () => {
        const methods = (RobotTimetable as any).methods
        const course = { key: 'D2', code: 'D2', ttCodes: ['D2'], name: 'Deutsch 2', hours: 2 }
        const firstGroup = {
            key: 'D2|D2-1C-GOS',
            title: 'D2-1C-GOS',
            courseGroups: [
                { key: 'd2-gos-1', class_name: 'D2-1C-GOS', course: 'D2', subject: 'D', weekday: 1, hour: 11 },
            ],
        }
        const secondGroup = {
            key: 'D2|D2-1C-HER',
            title: 'D2-1C-HER',
            courseGroups: [
                { key: 'd2-her-1', class_name: 'D2-1C-HER', course: 'D2', subject: 'D', weekday: 1, hour: 11 },
            ],
        }
        const freeGroup = {
            key: 'D2|D2-1K-GOS',
            title: 'D2-1K-GOS',
            courseGroups: [
                { key: 'd2-1k-1', class_name: 'D2-1K-GOS', course: 'D2', subject: 'D', weekday: 2, hour: 14 },
            ],
        }
        const ctx = {
            ...methods,
            selectedRobotTimetable: {
                slots: {
                    '1-11': {
                        key: 'M1',
                        code: 'M1',
                        sourceLabel: 'M1-1C-GOS',
                        courseGroup: { key: 'm1-gos-1', class_name: 'M1-1C-GOS', course: 'M1', subject: 'M', weekday: 1, hour: 11 },
                    },
                },
            },
            deselectedCourseGroupKeys: [],
            courseGroupItems() {
                return [firstGroup, secondGroup]
            },
        }

        expect(methods.courseAllGroupsNoLongerFitSelectedTimetable.call(ctx, course)).toBe(true)

        ctx.courseGroupItems = () => [firstGroup, freeGroup]

        expect(methods.courseAllGroupsNoLongerFitSelectedTimetable.call(ctx, course)).toBe(false)
    })

    it('does not render red conflicts when same weekday and hour have different dates', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            schoolHours: [
                { hour: 7, from: '14:45:00', until: '15:30:00' },
            ],
        }
        const assignedSlots = {
            '5-7': {
                code: 'ÖKO2',
                name: 'Ökonomie',
                sourceLabel: 'ÖKO2- BIE',
                courseGroup: {
                    class_name: 'ÖKO2- BIE',
                    weekday: 5,
                    hour: 7,
                    dates: ['2026-02-20'],
                },
            },
        }
        const candidateSet = {
            course: { code: 'INF3', name: 'Informatik 3' },
            options: [
                {
                    label: 'INF3-8AB-MAY',
                    courseGroups: [
                        {
                            class_name: 'INF3-8AB-MAY',
                            weekday: 5,
                            hour: 7,
                            dates: ['2026-02-27'],
                        },
                    ],
                },
            ],
        }

        const result = methods.assignedSlotsWithCourseConflicts.call(
            ctx,
            candidateSet,
            assignedSlots,
            new Set(['5-7']),
        )

        expect(result['5-7'].conflicts).toBeUndefined()
        expect(methods.candidateSetConflictLabels.call(ctx, candidateSet, assignedSlots, new Set(['5-7']))).toEqual([])
    })

    it('formats one-off appointments as indented problem details', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }

        const onlyOccasionalProblem = 'LPT - LPT: nur Einzeltermine (Di, 17.02.2026 20:25-21:10, Mi, 18.02.2026 17:05-17:50).'
        const outsideConstraintsProblem = 'D1 - Deutsch 1: D1-1K-GOS (Di 14.-15., 20:25-21:55) ist in deinen Zeitvorgaben nicht erlaubt.'
        const conflictProblem = 'LPT - LPT: Einzeltermin Di, 17.02.2026 20:25-21:10 <-> D1 - Deutsch 1 Di, 17.02.2026 20:25-21:10, Di, 17.02.2026 21:10-21:55 <-> D1 - Deutsch 1 Di, 17.02.2026 21:10-21:55 überschneidet sich mit D1 - Deutsch 1.'

        expect(methods.problemSummary.call(ctx, onlyOccasionalProblem)).toBe(
            'LPT - LPT: nur Einzeltermine.',
        )
        expect(methods.problemDetails.call(ctx, onlyOccasionalProblem)).toEqual([
            'Di, 17.02.2026 20:25-21:10',
            'Mi, 18.02.2026 17:05-17:50',
        ])
        expect(methods.problemSummary.call(ctx, outsideConstraintsProblem)).toBe(
            'D1 - Deutsch 1: D1-1K-GOS (Di 14.-15., 20:25-21:55) ist in deinen Zeitvorgaben nicht erlaubt.',
        )
        expect(methods.problemDetails.call(ctx, outsideConstraintsProblem)).toEqual([])
        expect(methods.problemSummary.call(ctx, conflictProblem)).toBe(
            'LPT - LPT: Einzeltermin überschneidet sich mit D1 - Deutsch 1.',
        )
        expect(methods.problemDetails.call(ctx, conflictProblem)).toEqual([
            'Di, 17.02.2026 20:25-21:10 <-> D1 - Deutsch 1 Di, 17.02.2026 20:25-21:10',
            'Di, 17.02.2026 21:10-21:55 <-> D1 - Deutsch 1 Di, 17.02.2026 21:10-21:55',
        ])
    })

    it('includes blocked TT group details for time constraint problems', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            weekdayOptions: [
                { shortTitle: 'Mo', value: 1 },
                { shortTitle: 'Di', value: 2 },
            ],
            timeOptions: [
                { shortTitle: '14. 20:25-21:10', value: 14 },
                { shortTitle: '15. 21:10-21:55', value: 15 },
            ],
            schoolHours: [
                { hour: 14, from: '20:25:00', until: '21:10:00' },
                { hour: 15, from: '21:10:00', until: '21:55:00' },
            ],
        }
        const problem = methods.candidateSetProblemMessage.call(ctx, {
            course: { code: 'D1', name: 'Deutsch 1' },
            matchingCourseGroupsCount: 2,
            regularCourseGroupsCount: 2,
            hasOnlyOccasionalMatches: false,
            regularCourseGroups: [
                { class_name: 'D1-1K-GOS', weekday: 2, hour: 14, dates_count: 18 },
                { class_name: 'D1-1K-GOS', weekday: 2, hour: 15, dates_count: 18 },
            ],
            occasionalCourseGroups: [],
        })

        expect(problem).toBe(
            'D1 - Deutsch 1: D1-1K-GOS (Di 14.-15., 20:25-21:55) ist in deinen Zeitvorgaben nicht erlaubt.',
        )
    })

    it('can select and deselect individual courses for timetable generation', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            deselectedCourseKeys: ['GW1'],
            deselectedCourseGroupKeys: [],
            configuredCourseGroups: [],
            availableCourses: [
                { key: 'INF1', code: 'INF1', name: 'Informatik 1', hours: 2 },
                { key: 'GW1', code: 'GW1', name: 'Geografie 1', hours: 4 },
            ],
            courseGroupItems(course) {
                return [{ title: `${course.code}-A` }]
            },
            clearGeneratedTimetables() {},
            saveLastRobotState() {},
        }

        expect(computed.selectedCourses.call(ctx).map(course => course.code)).toEqual(['INF1'])

        methods.setCourseSelected.call(ctx, ctx.availableCourses[1], true)
        expect(ctx.deselectedCourseKeys).toEqual([])
        expect(computed.selectedCourses.call(ctx).map(course => course.code)).toEqual(['INF1', 'GW1'])

        methods.setCourseSelected.call(ctx, ctx.availableCourses[0], false)
        methods.setCourseSelected.call(ctx, ctx.availableCourses[1], false)
        expect(computed.selectedCourses.call(ctx)).toEqual([])
    })

    it('checks missing and planned student courses in the course area by default', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                student_code: '100',
            },
            studentPlannedCourses: [
                { key: 'planned-gw2', code: 'GW2', ttCodes: ['GWB2'] },
            ],
            studentMissingCourses: [
                { key: 'missing-d2', code: 'D2', ttCodes: ['D2'] },
            ],
            deselectedCourseKeys: [],
            deselectedCourseGroupKeys: [],
            configuredCourseGroups: [],
            availableCourses: [
                { key: 'D2', code: 'D2', ttCodes: ['D2'], name: 'Deutsch 2', hours: 3 },
                { key: 'GW2', code: 'GW2', ttCodes: ['GWB2'], name: 'Geografie 2', hours: 2 },
                { key: 'INF2', code: 'INF2', ttCodes: ['INF2'], name: 'Informatik 2', hours: 2 },
            ],
            courseGroupItems(course) {
                return [{ title: `${course.code}-A` }]
            },
            clearGeneratedTimetables() {},
            saveLastRobotState() {},
        }

        methods.applyStudentPlannedCourseSelection.call(ctx)

        expect(ctx.deselectedCourseKeys).toEqual(['INF2'])
        expect(computed.selectedCourses.call(ctx).map(course => course.code)).toEqual(['D2', 'GW2'])
    })

    it('resets changed course selections to the selected student defaults', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                student_code: '100',
            },
            studentPlannedCourses: [
                { key: 'planned-s5', code: 'S5', ttCodes: ['SPA5'] },
            ],
            studentMissingCourses: [
                { key: 'missing-ch2', code: 'CH2', ttCodes: ['CH2'] },
            ],
            deselectedCourseKeys: ['CH2'],
            deselectedCourseGroupKeys: [],
            configuredCourseGroups: [],
            availableCourses: [
                { key: 'CH2', code: 'CH2', ttCodes: ['CH2'], name: 'Chemie 2', hours: 3 },
                { key: 'D6', code: 'D6', ttCodes: ['D6'], name: 'Deutsch 6', hours: 3 },
                { key: 'S5', code: 'S5', ttCodes: ['SPA5'], name: 'Spanisch 5', hours: 4 },
            ],
            courseGroupItems(course) {
                return [{ title: `${course.code}-A` }]
            },
            clearGeneratedTimetables() {
                this.cleared = true
            },
            saveLastRobotState() {
                this.saved = true
            },
        }

        expect(computed.courseSelectionResettable.call(ctx)).toBe(true)

        methods.resetCourseSelection.call(ctx)

        expect(ctx.deselectedCourseKeys).toEqual(['D6'])
        expect(computed.selectedCourses.call(ctx).map(course => course.code)).toEqual(['CH2', 'S5'])
        expect(computed.courseSelectionResettable.call(ctx)).toBe(false)
        expect(ctx.cleared).toBe(true)
        expect(ctx.saved).toBe(true)
    })

    it('resets regular and additional course selections with the student reset action', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const additionalCourse = { key: 'D2', code: 'D2', ttCodes: ['D2'], name: 'Deutsch 2', hours: 3 }
        const courseGroups = {
            D1: [{ title: 'D1-1C-GOS' }],
            D2: [{ title: 'D2-1C-HER' }],
        }
        const ctx = {
            ...methods,
            selectedStudent: {
                student_code: '100',
                school_level: '09',
                attendance_year: '1',
            },
            selection: {
                semester: 2,
                religion: 'Rk',
                branch: 'gymnasial',
                artsSubject: 'BE',
                language: 'F',
            },
            selectionDraft: {
                semester: 2,
                religion: 'Rk',
                branch: 'gymnasial',
                artsSubject: 'BE',
                language: 'F',
            },
            studentCompletedCourses: [],
            studentPlannedCourses: [
                { key: 'planned-d1', code: 'D1', ttCodes: ['D1'] },
            ],
            studentMissingCourses: [],
            availableCourses: [
                { key: 'D1', code: 'D1', ttCodes: ['D1'], name: 'Deutsch 1', hours: 3 },
                { key: 'M1', code: 'M1', ttCodes: ['M1'], name: 'Mathematik 1', hours: 4 },
            ],
            studentAdditionalCourses: [additionalCourse],
            additionalCourseSelectedKeys: ['D2'],
            additionalCourseTimetableRequired: true,
            deselectedCourseKeys: ['D1'],
            deselectedCourseGroupKeys: ['D1|D1-1C-GOS', 'D2|D2-1C-HER'],
            courseGroupItems(course) {
                return courseGroups[course.key] || []
            },
            clearGeneratedTimetables() {
                this.cleared = true
            },
            saveLastRobotState() {
                this.saved = true
            },
        }

        Object.defineProperty(ctx, 'courseSelectionResettable', {
            get() {
                return computed.courseSelectionResettable.call(this)
            },
        })
        Object.defineProperty(ctx, 'additionalCourseSelectionResettable', {
            get() {
                return computed.additionalCourseSelectionResettable.call(this)
            },
        })
        Object.defineProperty(ctx, 'studentSettingsResettable', {
            get() {
                return computed.studentSettingsResettable.call(this)
            },
        })

        expect(computed.studentCourseSelectionResettable.call(ctx)).toBe(true)

        methods.resetStudentCourseSelection.call(ctx)

        expect(ctx.selection).toEqual({
            semester: 1,
            religion: 'ETH',
            branch: 'wirtschaftskundlich',
            artsSubject: 'ME',
            language: 'L',
        })
        expect(ctx.selectionDraft).toEqual(ctx.selection)
        expect(ctx.deselectedCourseKeys).toEqual(['M1'])
        expect(ctx.deselectedCourseGroupKeys).toEqual([])
        expect(ctx.additionalCourseSelectedKeys).toEqual([])
        expect(ctx.additionalCourseTimetableRequired).toBe(false)
        expect(computed.studentCourseSelectionResettable.call(ctx)).toBe(false)
        expect(ctx.cleared).toBe(true)
        expect(ctx.saved).toBe(true)
    })

    it('resets robot selection defaults from a newly selected student', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            studentSelection: { studentCode: '100' },
            studentSelectionDraft: { studentCode: '200' },
            studentSelectionDefaultsPendingCode: null,
            selection: {
                semester: 1,
                religion: 'Rk',
                branch: 'gymnasial',
                artsSubject: 'BE',
                language: 'F',
            },
            selectionDraft: {
                semester: 1,
                religion: 'Rk',
                branch: 'gymnasial',
                artsSubject: 'BE',
                language: 'F',
            },
            robotStudents: [
                { student_code: '100', school_level: '09', attendance_year: '1' },
                { student_code: '200', school_level: '10', attendance_year: '2' },
            ],
            additionalCourseSelectedKeys: ['F3'],
            studentCompletedCoursesExpanded: true,
            clearGeneratedTimetables() {
                this.cleared = true
            },
            saveLastRobotState() {
                this.saved = true
            },
        }

        Object.defineProperty(ctx, 'selectedStudent', {
            get() {
                return this.robotStudents.find(student => student.student_code === this.studentSelection.studentCode) || null
            },
        })

        methods.updateStudentSelection.call(ctx)

        expect(ctx.selection).toEqual({
            semester: 4,
            religion: 'ETH',
            branch: 'wirtschaftskundlich',
            artsSubject: 'ME',
            language: 'L',
        })
        expect(ctx.selectionDraft).toEqual(ctx.selection)
        expect(ctx.studentSelectionDefaultsPendingCode).toBe('200')
        expect(ctx.additionalCourseSelectedKeys).toEqual([])
        expect(ctx.studentCompletedCoursesExpanded).toBe(false)
        expect(ctx.cleared).toBe(true)
        expect(ctx.saved).toBe(true)
    })

    it('syncs the embedded robot student from the overview student code', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            embeddedCourseCardsOnly: true,
            studentCode: '200',
            studentSelection: { studentCode: '100' },
            robotStudents: [
                { student_code: '100' },
                { student_code: '200' },
            ],
            applyStudentSelection(studentCode: string | null) {
                this.appliedStudentCode = studentCode
                this.studentSelection = { studentCode }
            },
        }

        methods.syncExternalStudentSelection.call(ctx)

        expect(ctx.appliedStudentCode).toBe('200')
        expect(ctx.studentSelection).toEqual({ studentCode: '200' })
    })

    it('marks embedded course cards ready only after courses are loaded', () => {
        const computed = (RobotTimetable as any).computed

        expect(computed.courseCardsReady.call({
            embeddedCourseCardsOnly: true,
            loading: false,
            availableCourses: [{ key: 'D1' }],
        })).toBe(true)

        expect(computed.courseCardsReady.call({
            embeddedCourseCardsOnly: true,
            loading: true,
            availableCourses: [{ key: 'D1' }],
        })).toBe(false)

        expect(computed.courseCardsReady.call({
            embeddedCourseCardsOnly: false,
            loading: false,
            availableCourses: [{ key: 'D1' }],
        })).toBe(false)

        expect(computed.courseCardsLoading.call({
            embeddedCourseCardsOnly: true,
            loading: true,
        })).toBe(true)

        expect(computed.courseCardsLoading.call({
            embeddedCourseCardsOnly: false,
            loading: true,
        })).toBe(false)
    })

    it('infers student selection defaults from recognized course history', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            studentCompletedCourses: [
                { subject: 'R1', grade: '5' },
                { subject: 'L1', grade: '5' },
                { subject: 'L2', grade: '5' },
                { subject: 'SPA3', grade: '5' },
                { subject: 'BE1', grade: 'B' },
            ],
        }

        expect(methods.selectedStudentDefaultSelection.call(
            ctx,
            { school_level: '11', attendance_year: '1' },
            { includeCourseHistory: true },
        )).toEqual({
            semester: 5,
            religion: 'Rk',
            branch: 'wirtschaftskundlich',
            artsSubject: 'BE',
            language: 'S',
        })
    })

    it('hides additional course cards until a timetable has been created', () => {
        const computed = (RobotTimetable as any).computed
        const ctx = {
            studentAdditionalCourses: [{ key: 'INF3' }],
            selectedRobotTimetable: null,
        }

        expect(computed.additionalCoursePanelVisible.call(ctx)).toBe(false)

        ctx.selectedRobotTimetable = { key: 'generated-1' }

        expect(computed.additionalCoursePanelVisible.call(ctx)).toBe(true)
    })

    it('requires the module two levels lower before selecting a dependent additional course', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            subjectMappings: [],
            additionalCourseSelectedKeys: [],
            courseGroupItems(course) {
                return [{ title: `${course.code}-A` }]
            },
            studentAdditionalCourses: [
                { key: 'INF3', code: 'INF3', ttCodes: ['INF3'], name: 'Informatik 3', hours: 2 },
                { key: 'INF4', code: 'INF4', ttCodes: ['INF4'], name: 'Informatik 4', hours: 2 },
                { key: 'INF5', code: 'INF5', ttCodes: ['INF5'], name: 'Informatik 5', hours: 2 },
            ],
        }

        expect(methods.additionalCourseSelectable.call(ctx, ctx.studentAdditionalCourses[0])).toBe(true)
        expect(methods.additionalCourseSelectable.call(ctx, ctx.studentAdditionalCourses[1])).toBe(true)
        expect(methods.additionalCourseSelectable.call(ctx, ctx.studentAdditionalCourses[2])).toBe(false)

        methods.setAdditionalCourseSelected.call(ctx, ctx.studentAdditionalCourses[2], true)
        expect(ctx.additionalCourseSelectedKeys).toEqual([])

        methods.setAdditionalCourseSelected.call(ctx, ctx.studentAdditionalCourses[0], true)
        expect(methods.additionalCourseSelectable.call(ctx, ctx.studentAdditionalCourses[2])).toBe(true)

        methods.setAdditionalCourseSelected.call(ctx, ctx.studentAdditionalCourses[2], true)
        expect(ctx.additionalCourseSelectedKeys).toEqual(['INF3', 'INF5'])

        methods.setAdditionalCourseSelected.call(ctx, ctx.studentAdditionalCourses[0], false)
        expect(ctx.additionalCourseSelectedKeys).toEqual([])
    })

    it('can select single imported course groups for an additional course', () => {
        const methods = (RobotTimetable as any).methods
        const course = { key: 'E7', code: 'E7', ttCodes: ['E7'], name: 'Englisch 7', hours: 1 }
        const courseGroups = [
            { title: 'E7-4Q-RIE' },
            { title: 'E7-7C-RAI' },
        ]
        const ctx = {
            ...methods,
            additionalCourseSelectedKeys: [],
            deselectedCourseGroupKeys: [],
            studentAdditionalCourses: [course],
            courseGroupItems() {
                return courseGroups
            },
            clearGeneratedTimetables() {
                this.cleared = true
            },
            saveLastRobotState() {
                this.saved = true
            },
        }

        methods.setAdditionalCourseGroupSelected.call(ctx, course, courseGroups[1], true)

        expect(ctx.additionalCourseSelectedKeys).toEqual(['E7'])
        expect(ctx.deselectedCourseGroupKeys).toEqual(['E7|E7-4Q-RIE'])
        expect(methods.additionalCourseGroupSelected.call(ctx, course, courseGroups[0])).toBe(false)
        expect(methods.additionalCourseGroupSelected.call(ctx, course, courseGroups[1])).toBe(true)
        expect(methods.additionalCoursePartiallySelected.call(ctx, course)).toBe(true)
        expect(methods.additionalCourseFullySelected.call(ctx, course)).toBe(false)
        expect(ctx.cleared).toBe(true)
        expect(ctx.saved).toBe(true)

        methods.setAdditionalCourseGroupSelected.call(ctx, course, courseGroups[0], true)

        expect(ctx.deselectedCourseGroupKeys).toEqual([])
        expect(methods.additionalCourseFullySelected.call(ctx, course)).toBe(true)

        methods.setAdditionalCourseGroupSelected.call(ctx, course, courseGroups[0], false)
        methods.setAdditionalCourseGroupSelected.call(ctx, course, courseGroups[1], false)

        expect(ctx.additionalCourseSelectedKeys).toEqual([])
        expect(ctx.deselectedCourseGroupKeys).toEqual([])
    })

    it('keeps the selected timetable visible when additional courses change', () => {
        const methods = (RobotTimetable as any).methods
        const course = { key: 'E7', code: 'E7', ttCodes: ['E7'], name: 'Englisch 7', hours: 1 }
        const ctx = {
            ...methods,
            additionalCourseSelectedKeys: [],
            deselectedCourseGroupKeys: [],
            selectedRobotTimetable: { key: 'generated-1', slots: {} },
            studentAdditionalCourses: [course],
            courseGroupItems() {
                return [{ title: 'E7-4Q-RIE' }]
            },
            clearGeneratedTimetables() {
                this.cleared = true
            },
            saveLastRobotState() {
                this.saved = true
            },
        }

        methods.setAdditionalCourseSelected.call(ctx, course, true)

        expect(ctx.additionalCourseSelectedKeys).toEqual(['E7'])
        expect(ctx.selectedRobotTimetable).toEqual({ key: 'generated-1', slots: {} })
        expect(ctx.cleared).toBeUndefined()
        expect(ctx.saved).toBe(true)
    })

    it('blocks red additional course rows from changing selection', () => {
        const methods = (RobotTimetable as any).methods
        const course = { key: 'INF2', code: 'INF2', ttCodes: ['INF2'], name: 'Informatik 2', hours: 2 }
        const ctx = {
            ...methods,
            additionalCourseSelectedKeys: ['INF2'],
            deselectedCourseGroupKeys: [],
            selectedRobotTimetable: {
                key: 'generated-1',
                missingAdditionalCourses: [{ key: 'INF2', code: 'INF2', ttCodes: ['INF2'] }],
                slots: {},
            },
            studentAdditionalCourses: [course],
            courseGroupItems() {
                return [{ title: 'INF2-4Q-GOS' }]
            },
            clearGeneratedTimetables() {
                this.cleared = true
            },
            saveLastRobotState() {
                this.saved = true
            },
        }

        expect(methods.additionalCourseInteractionDisabled.call(ctx, course)).toBe(true)

        methods.setAdditionalCourseSelected.call(ctx, course, false)

        expect(ctx.additionalCourseSelectedKeys).toEqual(['INF2'])
        expect(ctx.cleared).toBeUndefined()
        expect(ctx.saved).toBeUndefined()
    })

    it('resets changed additional course selections without touching regular course groups', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const additionalCourse = { key: 'INF2', code: 'INF2', ttCodes: ['INF2'], name: 'Informatik 2', hours: 2 }
        const courseGroups = {
            D1: [{ title: 'D1-1C-GOS' }],
            INF2: [
                { title: 'INF2-4Q-GOS' },
                { title: 'INF2-4Q-MAY' },
            ],
        }
        const ctx = {
            ...methods,
            additionalCourseSelectedKeys: ['INF2'],
            additionalCourseTimetableRequired: true,
            studentAdditionalCourses: [additionalCourse],
            deselectedCourseGroupKeys: ['D1|D1-1C-GOS', 'INF2|INF2-4Q-MAY'],
            courseGroupItems(course) {
                return courseGroups[course.key] || []
            },
            clearGeneratedTimetables() {
                this.cleared = true
            },
            saveLastRobotState() {
                this.saved = true
            },
        }

        expect(computed.additionalCourseSelectionResettable.call(ctx)).toBe(true)

        methods.resetAdditionalCourseSelection.call(ctx)

        expect(ctx.additionalCourseSelectedKeys).toEqual([])
        expect(ctx.additionalCourseTimetableRequired).toBe(false)
        expect(ctx.deselectedCourseGroupKeys).toEqual(['D1|D1-1C-GOS'])
        expect(computed.additionalCourseSelectionResettable.call(ctx)).toBe(false)
        expect(ctx.cleared).toBe(true)
        expect(ctx.saved).toBe(true)
    })

    it('splits available courses into two display columns', () => {
        const computed = (RobotTimetable as any).computed
        const ctx = {
            availableCourses: [
                { key: 'D1', code: 'D1' },
                { key: 'E1', code: 'E1' },
                { key: 'INF1', code: 'INF1' },
                { key: 'M1', code: 'M1' },
                { key: 'ME1', code: 'ME1' },
            ],
        }

        expect(computed.availableCourseColumns.call(ctx).map(courseColumn =>
            courseColumn.map(course => course.code),
        )).toEqual([
            ['D1', 'E1', 'INF1'],
            ['M1', 'ME1'],
        ])
    })

    it('keeps missing and planned student courses in separate display columns', () => {
        const computed = (RobotTimetable as any).computed
        const ctx = {
            selectedStudent: { student_code: '100' },
            studentMissingCourses: [
                { key: 'D1', code: 'D1' },
                { key: 'E1', code: 'E1' },
            ],
            studentPlannedCourses: [
                { key: 'M2', code: 'M2' },
            ],
        }

        expect(computed.regularCourseColumns.call(ctx).map(courseColumn => ({
            title: courseColumn.title,
            courses: courseColumn.courses.map(course => course.code),
        }))).toEqual([
            { title: 'Fehlende Kurse', courses: ['D1', 'E1'] },
            { title: 'Vorgesehene Kurse', courses: ['M2'] },
        ])
    })

    it('splits a single student course group into two display columns', () => {
        const computed = (RobotTimetable as any).computed
        const ctx = {
            selectedStudent: { student_code: '100' },
            studentMissingCourses: [],
            studentPlannedCourses: [
                { key: 'D1', code: 'D1' },
                { key: 'E1', code: 'E1' },
                { key: 'ETH1', code: 'ETH1' },
                { key: 'GW1', code: 'GW1' },
                { key: 'INF1', code: 'INF1' },
                { key: 'LPT', code: 'LPT' },
                { key: 'M1', code: 'M1' },
            ],
        }

        expect(computed.regularCourseColumns.call(ctx).map(courseColumn => ({
            title: courseColumn.title,
            courses: courseColumn.courses.map(course => course.code),
        }))).toEqual([
            { title: 'Vorgesehene Kurse', courses: ['D1', 'E1', 'ETH1', 'GW1'] },
            { title: '', courses: ['INF1', 'LPT', 'M1'] },
        ])
    })

    it('updates available courses when the robot selection changes', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            subjectMappings: [],
            religionOptions: computed.religionOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            subjectRows: [
                {
                    id: 1,
                    semester: 1,
                    branch: 'common',
                    json_code: 'D1',
                    json_subject: 'D',
                    name: 'Deutsch 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 2,
                    branch: 'common',
                    json_code: 'D2',
                    json_subject: 'D',
                    name: 'Deutsch 2',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 1,
                    branch: 'wirtschaftskundlich',
                    json_code: 'WIK1',
                    json_subject: 'WIK',
                    name: 'Wiku 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 4,
                    semester: 1,
                    branch: 'gymnasial',
                    json_code: 'GYM1',
                    json_subject: 'GYM',
                    name: 'Gym 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 5,
                    semester: 1,
                    branch: 'gymnasial',
                    json_code: 'ME1',
                    json_subject: 'ME',
                    name: 'Musikerziehung 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 6,
                    semester: 1,
                    branch: 'gymnasial',
                    json_code: 'BE1',
                    json_subject: 'BE',
                    name: 'Bildnerische Erziehung 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 7,
                    semester: 1,
                    branch: 'common',
                    json_code: 'R/ET1',
                    json_subject: 'R/ET',
                    name: 'Religion / Ethik 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 8,
                    semester: 1,
                    branch: 'common',
                    json_code: 'L/F/S1',
                    json_subject: 'L/F/S',
                    name: 'Sprache 1',
                    hours_per_week: 2,
                    is_active: true,
                },
            ],
        }
        const courses = () => computed.availableCourses.call(ctx)
        const courseCodes = () => courses().map(course => course.code)

        expect(courseCodes()).toEqual(['D1', 'ETH1', 'L1', 'WIK1'])

        ctx.selection.semester = 2
        expect(courseCodes()).toEqual(['D2'])

        ctx.selection.semester = 1
        ctx.selection.branch = 'gymnasial'
        expect(courseCodes()).toEqual(['D1', 'ETH1', 'GYM1', 'L1', 'ME1'])

        ctx.selection.artsSubject = 'BE'
        expect(courseCodes()).toEqual(['BE1', 'D1', 'ETH1', 'GYM1', 'L1'])

        ctx.selection.language = 'F'
        expect(courseCodes()).toEqual(['BE1', 'D1', 'ETH1', 'F1', 'GYM1'])

        const lateinCourseKey = courses().find(course => course.code === 'F1')?.key
        expect(lateinCourseKey).toContain('F1')

        ctx.selection.religion = 'Rk'
        expect(courseCodes()).toEqual(['BE1', 'D1', 'F1', 'GYM1', 'Rk1'])
        expect(courses().find(course => course.code === 'Rk1')?.key).toContain('Rk1')
    })

    it('shows only the selected individual language alternative', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selection: {
                semester: 5,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'F',
            },
            subjectMappings: [],
            languageOptions: computed.languageOptions.call({}),
            subjectRows: [
                {
                    id: 1,
                    semester: 5,
                    branch: 'common',
                    json_code: 'L4',
                    json_subject: 'L',
                    name: 'Latein 4',
                    hours_per_week: 4,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 5,
                    branch: 'common',
                    json_code: 'F4',
                    json_subject: 'F',
                    name: 'Französisch 4',
                    hours_per_week: 4,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 5,
                    branch: 'common',
                    json_code: 'S4',
                    json_subject: 'S',
                    name: 'Spanisch 4',
                    hours_per_week: 4,
                    is_active: true,
                },
                {
                    id: 4,
                    semester: 5,
                    branch: 'common',
                    json_code: 'D5',
                    json_subject: 'D',
                    name: 'Deutsch 5',
                    hours_per_week: 3,
                    is_active: true,
                },
            ],
        }

        expect(computed.availableCourses.call(ctx).map(course => course.code)).toEqual(['D5', 'F4'])

        ctx.selection.language = 'S'

        expect(computed.availableCourses.call(ctx).map(course => course.code)).toEqual(['D5', 'S4'])
    })

    it('shows planned student courses for the student semester without positively completed courses', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                class: '09_2',
            },
            selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            subjectMappings: [],
            languageOptions: computed.languageOptions.call({}),
            studentCompletedCourses: [
                { subject: 'INF1', grade: '1' },
                { subject: 'M2', grade: '1' },
                { subject: 'E2', grade: 'B' },
                { subject: 'GWB2', grade: '2' },
                { subject: 'D2', grade: '5' },
            ],
            subjectRows: [
                {
                    id: 1,
                    semester: 2,
                    branch: 'common',
                    json_code: 'D2',
                    json_subject: 'D',
                    name: 'Deutsch 2',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 2,
                    branch: 'common',
                    json_code: 'M2',
                    json_subject: 'M',
                    name: 'Mathematik 2',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 2,
                    branch: 'common',
                    json_code: 'E2',
                    json_subject: 'E',
                    name: 'Englisch 2',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 4,
                    semester: 2,
                    branch: 'common',
                    json_code: 'GW2',
                    json_subject: 'GW',
                    name: 'Geografie 2',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 5,
                    semester: 3,
                    branch: 'common',
                    json_code: 'INF2',
                    json_subject: 'INF',
                    name: 'Informatik 2',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 6,
                    semester: 3,
                    branch: 'common',
                    json_code: 'INF3',
                    json_subject: 'INF',
                    name: 'Informatik 3',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 7,
                    semester: 4,
                    branch: 'common',
                    json_code: 'D4',
                    json_subject: 'D',
                    name: 'Deutsch 4',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 8,
                    semester: 4,
                    branch: 'common',
                    json_code: 'M4',
                    json_subject: 'M',
                    name: 'Mathematik 4',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 9,
                    semester: 4,
                    branch: 'common',
                    json_code: 'E4',
                    json_subject: 'E',
                    name: 'Englisch 4',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 10,
                    semester: 4,
                    branch: 'common',
                    json_code: 'GW4',
                    json_subject: 'GW',
                    name: 'Geografie 4',
                    hours_per_week: 2,
                    is_active: true,
                },
            ],
        }

        expect(computed.studentPlannedCourses.call(ctx).map(course => course.code)).toEqual(['D2'])
        expect(computed.availableCourses.call(ctx).map(course => course.code)).toEqual(['D2'])
        expect(computed.regularCourseListTitle.call(ctx)).toBe('Fehlende Kurse + Vorgesehene Kurse')
        expect(computed.studentAdditionalCourses.call(ctx).map(course => course.code)).toEqual(['INF2', 'INF3', 'E4', 'GW4', 'M4'])
        expect(methods.completedCourseCountsAsDone.call(ctx, '4')).toBe(true)
        expect(methods.completedCourseCountsAsDone.call(ctx, 'B')).toBe(true)
        expect(methods.completedCourseCountsAsDone.call(ctx, '5')).toBe(false)
    })

    it('uses the selected semester for embedded student courses when the imported class has no school level', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                class: '1C',
            },
            selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            subjectMappings: [],
            studentCompletedCourses: [],
            subjectRows: [
                {
                    id: 1,
                    semester: 1,
                    branch: 'common',
                    json_code: 'D1',
                    json_subject: 'D',
                    name: 'Deutsch 1',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 1,
                    branch: 'common',
                    json_code: 'ETH1',
                    json_subject: 'ETH',
                    name: 'Ethik 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 2,
                    branch: 'common',
                    json_code: 'D2',
                    json_subject: 'D',
                    name: 'Deutsch 2',
                    hours_per_week: 3,
                    is_active: true,
                },
            ],
        }

        expect(computed.selectedStudentPlanningSemester.call(ctx)).toBe(1)
        expect(computed.studentPlannedCourses.call(ctx).map(course => course.code)).toEqual(['D1', 'ETH1'])
        expect(computed.availableCourses.call(ctx).map(course => course.code)).toEqual(['D1', 'ETH1'])
    })

    it('shows selected arts module one as an additional course without previous arts history', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                school_level: '11',
                attendance_year: '2',
            },
            selection: {
                semester: 6,
                religion: 'ETH',
                branch: 'gymnasial',
                artsSubject: 'ME',
                language: 'L',
            },
            subjectMappings: [],
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            studentCompletedCourses: [
                { subject: 'D6', grade: '3' },
                { subject: 'M6', grade: '2' },
            ],
            subjectRows: [
                {
                    id: 1,
                    semester: 7,
                    branch: 'gymnasial',
                    json_code: 'ME1',
                    json_subject: 'ME',
                    name: 'Musikerziehung 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 7,
                    branch: 'gymnasial',
                    json_code: 'BE1',
                    json_subject: 'BE',
                    name: 'Bildnerische Erziehung 1',
                    hours_per_week: 2,
                    is_active: true,
                },
            ],
        }

        expect(computed.studentAdditionalCourses.call(ctx).map(course => course.code)).toEqual(['ME1'])

        ctx.selection.branch = 'wirtschaftskundlich'

        expect(computed.studentAdditionalCourses.call(ctx).map(course => course.code)).toEqual([])
    })

    it('shows unfinished earlier Spanish modules as missing courses and keeps planned courses semester-scoped', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                school_level: '11',
                attendance_year: '2',
            },
            selection: {
                semester: 6,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'S',
            },
            subjectMappings: [],
            languageOptions: computed.languageOptions.call({}),
            studentCompletedCourses: [
                { subject: 'CH1', grade: 'B' },
                { subject: 'SPA2', grade: '3' },
                { subject: 'SPA3', grade: '2' },
            ],
            subjectRows: [
                {
                    id: 1,
                    semester: 2,
                    branch: 'common',
                    json_code: 'S1',
                    json_subject: 'S',
                    name: 'Spanisch 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 3,
                    branch: 'common',
                    json_code: 'S2',
                    json_subject: 'S',
                    name: 'Spanisch 2',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 4,
                    branch: 'common',
                    json_code: 'S3',
                    json_subject: 'S',
                    name: 'Spanisch 3',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 4,
                    semester: 5,
                    branch: 'common',
                    json_code: 'S4',
                    json_subject: 'S',
                    name: 'Spanisch 4',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 5,
                    semester: 4,
                    branch: 'common',
                    json_code: 'CH1',
                    json_subject: 'CH',
                    name: 'Chemie 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 6,
                    semester: 5,
                    branch: 'common',
                    json_code: 'CH2',
                    json_subject: 'CH',
                    name: 'Chemie 2',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 7,
                    semester: 6,
                    branch: 'common',
                    json_code: 'D6',
                    json_subject: 'D',
                    name: 'Deutsch 6',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 8,
                    semester: 6,
                    branch: 'common',
                    json_code: 'S5',
                    json_subject: 'S',
                    name: 'Spanisch 5',
                    hours_per_week: 2,
                    is_active: true,
                },
            ],
        }

        expect(computed.studentMissingCourses.call(ctx).map(course => course.code)).toEqual(['CH2', 'S4'])
        expect(computed.studentPlannedCourses.call(ctx).map(course => course.code)).toEqual(['D6', 'S5'])
        expect(computed.availableCourses.call(ctx).map(course => course.code)).toEqual(['CH2', 'D6', 'S4', 'S5'])
    })

    it('shows missing ethics modules from shared religion and ethics subject rows', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                school_level: '11',
                attendance_year: '2',
            },
            selection: {
                semester: 6,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'S',
            },
            subjectMappings: [],
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            studentCompletedCourses: [
                { subject: 'ETH1', grade: '1' },
                { subject: 'ETH3', grade: '1' },
                { subject: 'ETH4', grade: '' },
            ],
            subjectRows: [
                {
                    id: 1,
                    semester: 1,
                    branch: 'common',
                    json_code: 'R/ET1',
                    json_subject: 'R/ET',
                    name: 'Religion / Ethik 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 2,
                    branch: 'common',
                    json_code: 'R/ET2',
                    json_subject: 'R/ET',
                    name: 'Religion / Ethik 2',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 3,
                    branch: 'common',
                    json_code: 'R/ET3',
                    json_subject: 'R/ET',
                    name: 'Religion / Ethik 3',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 4,
                    semester: 4,
                    branch: 'common',
                    json_code: 'R/ET4',
                    json_subject: 'R/ET',
                    name: 'Religion / Ethik 4',
                    hours_per_week: 2,
                    is_active: true,
                },
            ],
        }

        expect(computed.studentMissingCourses.call(ctx).map(course => course.code)).toEqual(['ETH2'])
    })

    it('continues religion or ethics modules after completed alternate modules', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                school_level: '11',
                attendance_year: '2',
            },
            selection: {
                semester: 6,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'S',
            },
            subjectMappings: [],
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            studentCompletedCourses: [
                { subject: 'R1', grade: '2' },
                { subject: 'R2', grade: '3' },
            ],
            subjectRows: [
                {
                    id: 1,
                    semester: 1,
                    branch: 'common',
                    json_code: 'R/ET1',
                    json_subject: 'R/ET',
                    name: 'Religion / Ethik 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 2,
                    branch: 'common',
                    json_code: 'R/ET2',
                    json_subject: 'R/ET',
                    name: 'Religion / Ethik 2',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 3,
                    branch: 'common',
                    json_code: 'R/ET3',
                    json_subject: 'R/ET',
                    name: 'Religion / Ethik 3',
                    hours_per_week: 2,
                    is_active: true,
                },
            ],
        }

        expect(computed.studentMissingCourses.call(ctx).map(course => course.code)).toEqual(['ETH3'])

        ctx.selection.religion = 'Rk'

        expect(computed.studentMissingCourses.call(ctx).map(course => course.code)).toEqual(['Rk3'])
    })

    it('splits ordinary slash-separated subject codes into separate robot courses', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selection: {
                semester: 8,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            subjectMappings: [
                { json_subject: 'ÖKO', tt_subject: 'OKON', is_active: true },
            ],
            languageOptions: [
                { title: 'L - Latein', value: 'L' },
            ],
            subjectRows: [
                {
                    id: 1,
                    semester: 8,
                    branch: 'common',
                    json_code: 'ÖKO2 / ÖKO3',
                    json_subject: 'ÖKO',
                    name: 'Ökologie',
                    hours_per_week: 4,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 8,
                    branch: 'common',
                    json_code: 'L/F/S2',
                    json_subject: 'L/F/S',
                    name: 'Sprache',
                    hours_per_week: 2,
                    is_active: true,
                },
            ],
        }

        const courses = computed.availableCourses.call(ctx)

        expect(courses.map(course => course.code)).toEqual(['L2', 'ÖKO2', 'ÖKO3'])
        expect(courses.find(course => course.code === 'ÖKO2').hours).toBe(2)
        expect(courses.find(course => course.code === 'ÖKO3').hours).toBe(2)
        expect(courses.find(course => course.code === 'ÖKO2').ttCodes).toEqual(['OKON2'])
        expect(courses.find(course => course.code === 'ÖKO3').ttCodes).toEqual(['OKON3'])
    })

    it('generates ÖKO2 and ÖKO3 after splitting shared subject hours', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'ÖKO2', code: 'ÖKO2', name: 'Ökonomie', branch: 'Wiku', hours: 2, ttCodes: ['OKON2'] },
                { key: 'ÖKO3', code: 'ÖKO3', name: 'Ökonomie', branch: 'Wiku', hours: 2, ttCodes: ['OKON3'] },
            ],
            configuredCourseGroups: [
                { key: 'oko2-7', class_name: 'ÖKO2- BIE', course: 'OKON', subject: 'OKON', weekday: 5, hour: 7 },
                { key: 'oko2-13', class_name: 'ÖKO2- BIE', course: 'OKON', subject: 'OKON', weekday: 5, hour: 13 },
                { key: 'oko3-8', class_name: 'ÖKO3- BIE', course: 'OKON', subject: 'OKON', weekday: 5, hour: 8 },
                { key: 'oko3-9', class_name: 'ÖKO3- BIE', course: 'OKON', subject: 'OKON', weekday: 5, hour: 9 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationProblems).toEqual([])
        expect(ctx.generatedTimetables).toHaveLength(1)
        expect(Object.keys(ctx.generatedTimetables[0].slots).sort()).toEqual(['5-13', '5-7', '5-8', '5-9'])
    })

    it('shows Monday to Friday and only shows Saturday when selected', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
                { title: 'Mittwoch', shortTitle: 'Mi', value: 3 },
                { title: 'Donnerstag', shortTitle: 'Do', value: 4 },
                { title: 'Freitag', shortTitle: 'Fr', value: 5 },
                { title: 'Samstag', shortTitle: 'Sa', value: 6 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5],
            },
        }

        expect(computed.generatedWeekdays.call(ctx).map(weekday => weekday.shortTitle)).toEqual([
            'Mo',
            'Di',
            'Mi',
            'Do',
            'Fr',
        ])

        ctx.constraints.availableWeekdays.push(6)

        expect(computed.generatedWeekdays.call(ctx).map(weekday => weekday.shortTitle)).toEqual([
            'Mo',
            'Di',
            'Mi',
            'Do',
            'Fr',
            'Sa',
        ])
    })

    it('creates best possible timetables and reports problems when courses overlap', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'INF1', code: 'INF1', name: 'Informatik 1', branch: 'alle', hours: 2 },
                { key: 'GW1', code: 'GW1', ttCode: 'GWB1', name: 'Geografie 1', branch: 'alle', hours: 2 },
            ],
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', weekday: 1, hour: 1 },
                { key: 'inf-tu', class_name: 'INF1-Grp1-KRO', weekday: 1, hour: 3 },
                { key: 'gw-mo', class_name: 'GWB1-1C-HÖF', course: 'GWB', subject: 'GWB', weekday: 1, hour: 1 },
                { key: 'gw-tu', class_name: 'GWB1-1C-HÖF', course: 'GWB', subject: 'GWB', weekday: 1, hour: 2 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
            ],
            timeOptions: [
                { title: '1. Stunde', shortTitle: '1. Stunde', value: 1 },
                { title: '2. Stunde', shortTitle: '2. Stunde', value: 2 },
                { title: '3. Stunde', shortTitle: '3. Stunde', value: 3 },
            ],
            schoolHours: [
                { hour: 1, from: '18:45:00', until: '19:30:00' },
                { hour: 2, from: '19:30:00', until: '20:15:00' },
                { hour: 3, from: '20:25:00', until: '21:10:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables.length).toBeGreaterThan(0)
        expect(Object.keys(ctx.generatedTimetables[0].slots)).toHaveLength(3)
        expect(ctx.generatedTimetables[0].problems.length).toBeGreaterThan(0)
        expect(ctx.generationProblems.length).toBeGreaterThan(0)
        expect(methods.generatedSlotConflicts.call(ctx, ctx.generatedTimetables[0].slots['1-1'])).toEqual([
            'GW1 - Geografie 1 18:45-19:30',
        ])
        expect(ctx.generatedTimetables[0].slots['1-2'].code).toBe('GW1')
        expect(ctx.generatedTimetables[0].slots['1-2'].isConflictPreview).toBe(true)
        expect(methods.generatedSlotConflicts.call(ctx, ctx.generatedTimetables[0].slots['1-2'])).toEqual([])
    })

    it('shows the mapped semester in student labels', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }

        expect(methods.studentSemesterLabel.call(ctx, { class: '09_1' })).toBe('Semester 1')
        expect(methods.studentSemesterLabel.call(ctx, { class: '12_2' })).toBe('Semester 8')
        expect(methods.studentSemesterLabel.call(ctx, { class: '1A' })).toBe('')
        expect(methods.studentSemesterLabel.call(ctx, { class: '1A', school_level: '11', attendance_year: '2' }))
            .toBe('Semester 6')
        expect(methods.studentOptionTitle.call(ctx, {
            class: '09_2',
            last_name: 'Alpha',
            first_name: 'Anna',
            student_code: '100',
        })).toBe('09_2 · Alpha Anna · Semester 2')
    })

    it('prefers a free regular weekday when single appointments complete a partial course option', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'BU2', code: 'BU2', name: 'Biologie 2', branch: 'alle', hours: 4 },
                { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                { key: 'bu-4a-mo', class_name: 'BU2-4A-KOW', course: 'BU', subject: 'BU', weekday: 1, hour: 1, dates_count: 18 },
                { key: 'bu-4a-tu', class_name: 'BU2-4A-KOW', course: 'BU', subject: 'BU', weekday: 2, hour: 1, dates_count: 18 },
                { key: 'bu-4a-we-1', class_name: 'BU2-4A-KOW', course: 'BU', subject: 'BU', weekday: 3, hour: 1, dates_count: 18 },
                { key: 'bu-4a-we-2', class_name: 'BU2-4A-KOW', course: 'BU', subject: 'BU', weekday: 3, hour: 2, dates_count: 18 },
                { key: 'bu-2s-fr-1', class_name: 'BU2-2S-WIN', course: 'BU', subject: 'BU', weekday: 5, hour: 1, dates_count: 18 },
                { key: 'bu-2s-fr-2', class_name: 'BU2-2S-WIN', course: 'BU', subject: 'BU', weekday: 5, hour: 2, dates_count: 18 },
                { key: 'bu-2s-single-1', class_name: 'BU2-2S-WIN', course: 'BU', subject: 'BU', weekday: 5, hour: 3, dates: ['2026-03-06'], dates_count: 1 },
                { key: 'bu-2s-single-2', class_name: 'BU2-2S-WIN', course: 'BU', subject: 'BU', weekday: 5, hour: 4, dates: ['2026-03-06'], dates_count: 1 },
                { key: 'd-mo', class_name: 'D1-1C-GOS', course: 'D1', subject: 'D', weekday: 1, hour: 3, dates_count: 18 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2, 3, 4],
            },
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
                { title: 'Mittwoch', shortTitle: 'Mi', value: 3 },
                { title: 'Donnerstag', shortTitle: 'Do', value: 4 },
                { title: 'Freitag', shortTitle: 'Fr', value: 5 },
            ],
            timeOptions: [
                { title: '1. Stunde', shortTitle: '1. Stunde', value: 1 },
                { title: '2. Stunde', shortTitle: '2. Stunde', value: 2 },
                { title: '3. Stunde', shortTitle: '3. Stunde', value: 3 },
                { title: '4. Stunde', shortTitle: '4. Stunde', value: 4 },
            ],
            schoolHours: [
                { hour: 1, from: '17:50:00', until: '18:35:00' },
                { hour: 2, from: '18:45:00', until: '19:30:00' },
                { hour: 3, from: '19:30:00', until: '20:15:00' },
                { hour: 4, from: '20:25:00', until: '21:10:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        const firstTimetable = ctx.generatedTimetables[0]
        const regularBiologySlots = Object.values(firstTimetable.slots)
            .filter(slot => slot?.code === 'BU2')

        expect(ctx.generationError).toBe('')
        expect(regularBiologySlots).toHaveLength(2)
        expect(regularBiologySlots.map(slot => slot.sourceLabel)).toEqual([
            'BU2-2S-WIN',
            'BU2-2S-WIN',
        ])
        expect(Object.keys(firstTimetable.slots).some(slotKey => slotKey.startsWith('3-'))).toBe(false)
        expect(firstTimetable.occasionalAppointments.filter(appointment => appointment.sourceLabel === 'BU2-2S-WIN')).toHaveLength(2)
    })

    it('does not drop a date-different same-slot course', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'E4', code: 'E4', name: 'Englisch 4', branch: 'alle', hours: 1 },
                { key: 'M4', code: 'M4', name: 'Mathematik 4', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                { key: 'e4-sa', class_name: 'E4-2S-KÖN', course: 'E4', subject: 'E', weekday: 6, hour: 4, dates: ['2026-02-21', '2026-03-07', '2026-03-21'], dates_count: 3 },
                { key: 'm4-sa', class_name: 'M4-3U-ALT', course: 'M4', subject: 'M', weekday: 6, hour: 4, dates: ['2026-02-28', '2026-03-14', '2026-03-28'], dates_count: 3 },
                { key: 'm4-fr', class_name: 'M4-3R-SCHM', course: 'M4', subject: 'M', weekday: 5, hour: 4, dates_count: 18 },
            ],
            constraints: {
                availableWeekdays: [5, 6],
                excludedWeekdayTimes: [],
                availableTimes: [4],
            },
            weekdayOptions: [
                { title: 'Freitag', shortTitle: 'Fr', value: 5 },
                { title: 'Samstag', shortTitle: 'Sa', value: 6 },
            ],
            timeOptions: [
                { title: '4. Stunde', shortTitle: '4. Stunde', value: 4 },
            ],
            schoolHours: [
                { hour: 4, from: '20:25:00', until: '21:10:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        const scheduledCodes = Object.values(ctx.generatedTimetables[0].slots)
            .filter(slot => slot && !slot.isConflictPreview)
            .flatMap(slot => methods.assignedSlotEntries.call(ctx, slot))
            .map(slot => slot.code)
            .sort()

        expect(scheduledCodes).toEqual(['E4', 'M4'])
        expect(ctx.generatedTimetables[0].slots['6-4'].code).toBe('E4')
        expect(ctx.generatedTimetables[0].slots['6-4'].sameSlotEntries.map(entry => entry.code)).toEqual(['M4'])
        expect(ctx.generatedTimetables[0].slots['5-4']).toBeUndefined()
    })

    it('shows every complete regular-green timetable in addition to the top tier', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'A1', code: 'A1', name: 'Course A', branch: 'alle', hours: 1 },
                { key: 'B1', code: 'B1', name: 'Course B', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                { key: 'a-mo', class_name: 'A1-MO', course: 'A1', subject: 'A', weekday: 1, hour: 1, dates_count: 18 },
                { key: 'a-tu', class_name: 'A1-TU', course: 'A1', subject: 'A', weekday: 2, hour: 1, dates_count: 18 },
                { key: 'b-mo', class_name: 'B1-MO', course: 'B1', subject: 'B', weekday: 1, hour: 2, dates_count: 18 },
                { key: 'b-tu', class_name: 'B1-TU', course: 'B1', subject: 'B', weekday: 2, hour: 2, dates_count: 18 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2],
            },
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
                { title: 'Mittwoch', shortTitle: 'Mi', value: 3 },
            ],
            timeOptions: [
                { title: '1. Stunde', shortTitle: '1. Stunde', value: 1 },
                { title: '2. Stunde', shortTitle: '2. Stunde', value: 2 },
            ],
            schoolHours: [
                { hour: 1, from: '17:50:00', until: '18:35:00' },
                { hour: 2, from: '18:45:00', until: '19:30:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generatedTimetables).toHaveLength(4)
        expect(ctx.generatedTimetables.every(timetable => timetable.problems.length === 0)).toBe(true)
        expect(ctx.generatedTimetables.map(timetable => Object.keys(timetable.slots).sort())).toEqual([
            ['1-1', '1-2'],
            ['2-1', '2-2'],
            ['1-1', '2-2'],
            ['1-2', '2-1'],
        ])
    })

    it('keeps only the all-green timetables with the fewest used days when there are many results', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'A1', code: 'A1', name: 'Course A', branch: 'alle', hours: 1 },
                { key: 'B1', code: 'B1', name: 'Course B', branch: 'alle', hours: 1 },
                { key: 'C1', code: 'C1', name: 'Course C', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                { key: 'a-mo', class_name: 'A1-MO', course: 'A1', subject: 'A', weekday: 1, hour: 1, dates_count: 18 },
                { key: 'a-tu', class_name: 'A1-TU', course: 'A1', subject: 'A', weekday: 2, hour: 1, dates_count: 18 },
                { key: 'a-we', class_name: 'A1-WE', course: 'A1', subject: 'A', weekday: 3, hour: 1, dates_count: 18 },
                { key: 'b-mo', class_name: 'B1-MO', course: 'B1', subject: 'B', weekday: 1, hour: 2, dates_count: 18 },
                { key: 'b-tu', class_name: 'B1-TU', course: 'B1', subject: 'B', weekday: 2, hour: 2, dates_count: 18 },
                { key: 'b-we', class_name: 'B1-WE', course: 'B1', subject: 'B', weekday: 3, hour: 2, dates_count: 18 },
                { key: 'c-mo', class_name: 'C1-MO', course: 'C1', subject: 'C', weekday: 1, hour: 3, dates_count: 18 },
                { key: 'c-tu', class_name: 'C1-TU', course: 'C1', subject: 'C', weekday: 2, hour: 3, dates_count: 18 },
                { key: 'c-we', class_name: 'C1-WE', course: 'C1', subject: 'C', weekday: 3, hour: 3, dates_count: 18 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2, 3],
            },
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
                { title: 'Mittwoch', shortTitle: 'Mi', value: 3 },
            ],
            timeOptions: [
                { title: '1. Stunde', shortTitle: '1. Stunde', value: 1 },
                { title: '2. Stunde', shortTitle: '2. Stunde', value: 2 },
                { title: '3. Stunde', shortTitle: '3. Stunde', value: 3 },
            ],
            schoolHours: [
                { hour: 1, from: '17:50:00', until: '18:35:00' },
                { hour: 2, from: '18:45:00', until: '19:30:00' },
                { hour: 3, from: '19:30:00', until: '20:15:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        const usedWeekdays = ctx.generatedTimetables.map(timetable =>
            [...new Set(Object.values(timetable.slots)
                .map(slot => Number(slot?.courseGroup?.weekday)))]
                .sort(),
        )

        expect(ctx.generatedTimetables).toHaveLength(3)
        expect(ctx.generatedTimetables.every(timetable => timetable.problems.length === 0)).toBe(true)
        expect(usedWeekdays).toEqual([[1], [2], [3]])
    })

    it('treats shorter regular TT variants as valid course options', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'F3', code: 'F3', name: 'Französisch 3', branch: 'alle', hours: 4 },
            ],
            configuredCourseGroups: [
                { key: 'f3-4a-th-11', class_name: 'F3-4A-NIE', course: 'F', subject: 'F', weekday: 4, hour: 11, dates_count: 18 },
                { key: 'f3-4a-th-12', class_name: 'F3-4A-NIE', course: 'F', subject: 'F', weekday: 4, hour: 12, dates_count: 18 },
                { key: 'f3-4a-fr-10', class_name: 'F3-4A-NIE', course: 'F', subject: 'F', weekday: 5, hour: 10, dates_count: 18 },
                { key: 'f3-4a-fr-11', class_name: 'F3-4A-NIE', course: 'F', subject: 'F', weekday: 5, hour: 11, dates_count: 18 },
                { key: 'f3-3ru-fr-10', class_name: 'F3-3RU+4F-NIE', course: 'F', subject: 'F', weekday: 5, hour: 10, dates_count: 18 },
                { key: 'f3-3ru-fr-11', class_name: 'F3-3RU+4F-NIE', course: 'F', subject: 'F', weekday: 5, hour: 11, dates_count: 18 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5],
                excludedWeekdayTimes: [],
                availableTimes: [10, 11, 12],
            },
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
                { title: 'Mittwoch', shortTitle: 'Mi', value: 3 },
                { title: 'Donnerstag', shortTitle: 'Do', value: 4 },
                { title: 'Freitag', shortTitle: 'Fr', value: 5 },
            ],
            timeOptions: [
                { title: '10. Stunde', shortTitle: '10. Stunde', value: 10 },
                { title: '11. Stunde', shortTitle: '11. Stunde', value: 11 },
                { title: '12. Stunde', shortTitle: '12. Stunde', value: 12 },
            ],
            schoolHours: [
                { hour: 10, from: '17:05:00', until: '17:50:00' },
                { hour: 11, from: '17:50:00', until: '18:35:00' },
                { hour: 12, from: '18:45:00', until: '19:30:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables.map(timetable => timetable.problems)).toEqual([[], []])
        expect(ctx.generatedTimetables.map(timetable =>
            Object.values(timetable.slots)[0]?.sourceLabel,
        )).toEqual([
            'F3-3RU+4F-NIE',
            'F3-4A-NIE',
        ])
        expect(Object.values(ctx.generatedTimetables[0].slots)
            .every(slot => slot.isDistanceLearningCourse === true)).toBe(true)
        expect(Object.values(ctx.generatedTimetables[1].slots)
            .some(slot => slot.isDistanceLearningCourse === true)).toBe(false)
        expect(methods.courseGroupDistanceLearning.call(ctx, ctx.selectedCourses[0], { title: 'F3-3RU+4F-NIE' })).toBe(true)
        expect(methods.courseGroupDistanceLearning.call(ctx, ctx.selectedCourses[0], { title: 'F3-4A-NIE' })).toBe(false)
    })

    it('marks weekly recurrence intervals on courses, groups, slots, and appointments', () => {
        const methods = (RobotTimetable as any).methods
        const course = { key: 'E7', code: 'E7', name: 'Englisch 7', branch: 'alle', hours: 1 }
        const courseGroup = {
            key: 'e7',
            class_name: 'E7-7C-RAI',
            course: 'E7',
            subject: 'E',
            weekday: 1,
            hour: 1,
            recurrence_interval: 2,
            recurrence_label: '2-wöchig',
            dates: ['2026-03-02', '2026-03-16', '2026-03-30'],
        }
        const ctx = {
            ...methods,
            configuredCourseGroups: [courseGroup],
        }

        const group = methods.courseGroupItems.call(ctx, course)[0]

        expect(methods.courseWeekMarker.call(ctx, course)).toBe('2-w')
        expect(methods.courseGroupWeekMarker.call(ctx, group)).toBe('2-w')
        expect(methods.slotWeekMarker.call(ctx, { courseGroup })).toBe('2-w')
        expect(methods.appointmentWeekMarker.call(ctx, { courseGroup })).toBe('2-w')
        expect(methods.weekIntervalFromDates.call(ctx, ['2026-03-02', '2026-03-09', '2026-03-16'])).toBe(1)
    })

    it('hides redundant weekly markers in expanded weekly-only course group schedules', () => {
        const methods = (RobotTimetable as any).methods
        const course = { key: 'E1', code: 'E1', name: 'Englisch 1', branch: 'alle', hours: 4 }
        const ctx = {
            ...methods,
            configuredCourseGroups: [
                {
                    key: 'e1-rai-mo-14',
                    class_name: 'E1-1C-RAI',
                    course: 'E1',
                    subject: 'E',
                    weekday: 1,
                    hour: 14,
                    recurrence_interval: 1,
                },
                {
                    key: 'e1-rai-mo-15',
                    class_name: 'E1-1C-RAI',
                    course: 'E1',
                    subject: 'E',
                    weekday: 1,
                    hour: 15,
                    recurrence_interval: 1,
                },
                {
                    key: 'e1-rai-di-12',
                    class_name: 'E1-1C-RAI',
                    course: 'E1',
                    subject: 'E',
                    weekday: 2,
                    hour: 12,
                    recurrence_interval: 1,
                },
                {
                    key: 'e1-rai-di-13',
                    class_name: 'E1-1C-RAI',
                    course: 'E1',
                    subject: 'E',
                    weekday: 2,
                    hour: 13,
                    recurrence_interval: 1,
                },
                {
                    key: 'e1-scho-mo-14',
                    class_name: 'E1-1C-SCHO',
                    course: 'E1',
                    subject: 'E',
                    weekday: 1,
                    hour: 14,
                    recurrence_interval: 1,
                },
                {
                    key: 'e1-scho-mo-15',
                    class_name: 'E1-1C-SCHO',
                    course: 'E1',
                    subject: 'E',
                    weekday: 1,
                    hour: 15,
                    recurrence_interval: 1,
                },
            ],
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
            ],
            timeOptions: [
                { title: '12. Stunde', shortTitle: '12.', value: 12 },
                { title: '13. Stunde', shortTitle: '13.', value: 13 },
                { title: '14. Stunde', shortTitle: '14.', value: 14 },
                { title: '15. Stunde', shortTitle: '15.', value: 15 },
            ],
            schoolHours: [
                { hour: 12, from: '18:45:00', until: '19:30:00' },
                { hour: 13, from: '19:30:00', until: '20:15:00' },
                { hour: 14, from: '20:25:00', until: '21:10:00' },
                { hour: 15, from: '21:10:00', until: '21:55:00' },
            ],
        }

        const groups = methods.courseGroupItems.call(ctx, course)
        const raiGroup = groups.find(group => group.title === 'E1-1C-RAI')
        const schoGroup = groups.find(group => group.title === 'E1-1C-SCHO')

        expect(methods.courseGroupWeekMarker.call(ctx, raiGroup)).toBe('1-w')
        expect(raiGroup.meta).toBe('Mo 14.-15., 20:25-21:55, Di 12.-13., 18:45-20:15')
        expect(schoGroup.meta).toBe('Mo 14.-15., 20:25-21:55')
    })

    it('hides redundant weekly markers in the generated timetable table', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }
        const weeklySlot = {
            key: 'E1',
            code: 'E1',
            sourceLabel: 'E1-1C-RAI',
            courseGroup: {
                class_name: 'E1-1C-RAI',
                course: 'E1',
                weekday: 1,
                hour: 14,
                recurrence_interval: 1,
            },
        }
        const timetable = {
            slots: {
                '1-14': weeklySlot,
                '1-15': {
                    key: 'E1',
                    code: 'E1',
                    sourceLabel: 'E1-1C-RAI',
                    courseGroup: {
                        class_name: 'E1-1C-RAI',
                        course: 'E1',
                        weekday: 1,
                        hour: 15,
                        recurrence_interval: 1,
                    },
                },
            },
            occasionalAppointments: [],
        }

        expect(methods.slotWeekMarker.call(ctx, weeklySlot)).toBe('1-w')
        expect(methods.generatedSlotWeekMarker.call(ctx, weeklySlot, timetable)).toBe('')
    })

    it('shows weekly markers in the generated timetable table when a course has mixed intervals', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }
        const weeklySlot = {
            key: 'D1',
            code: 'D1',
            sourceLabel: 'D1-1C-GOS',
            courseGroup: {
                class_name: 'D1-1C-GOS',
                course: 'D1',
                weekday: 2,
                hour: 14,
                recurrence_interval: 1,
            },
        }
        const twoWeeklySlot = {
            key: 'D1',
            code: 'D1',
            sourceLabel: 'D1-1C-GOS',
            courseGroup: {
                class_name: 'D1-1C-GOS',
                course: 'D1',
                weekday: 2,
                hour: 15,
                recurrence_interval: 2,
            },
        }
        const weeklyAppointment = {
            key: 'd1-app-1',
            courseKey: 'D1',
            code: 'D1',
            sourceLabel: 'D1-1C-GOS',
            dateTimeLabel: 'Di, 03.03.2026 16. 21:55-22:40',
            date: '2026-03-03',
            dateLabel: 'Di, 03.03.2026',
            weekday: 2,
            hour: 16,
            sortValue: '2026-03-03|02|16|D1',
            recurrence_interval: 1,
        }
        const timetable = {
            slots: {
                '2-14': weeklySlot,
                '2-15': twoWeeklySlot,
            },
            selectedOccasionalAppointmentGroups: {},
            occasionalAppointments: [weeklyAppointment],
        }

        expect(methods.generatedSlotWeekMarker.call(ctx, weeklySlot, timetable)).toBe('1-w')
        expect(methods.generatedSlotWeekMarker.call(ctx, twoWeeklySlot, timetable)).toBe('2-w')

        const appointmentGroup = methods.groupedOccasionalAppointments.call(ctx, timetable)[0]
        methods.setOccasionalAppointmentGroupSelected.call(ctx, timetable, appointmentGroup, true)

        expect(appointmentGroup.weekMarker).toBe('1-w')
        expect(methods.generatedCellOccasionalMarkers.call(ctx, timetable, 2, 16)).toEqual([
            { key: 'd1-app-1', code: 'D1', weekMarker: '1-w' },
        ])
    })

    it('splits mixed weekly recurrence in expanded course group rows and marks half-load groups as FU', () => {
        const methods = (RobotTimetable as any).methods
        const course = { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 3 }
        const ctx = {
            ...methods,
            configuredCourseGroups: [
                {
                    key: 'd1-1k-mo-12',
                    class_name: 'D1-1C-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 1,
                    hour: 12,
                    recurrence_interval: 1,
                },
                {
                    key: 'd1-1k-14',
                    class_name: 'D1-1C-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 14,
                    recurrence_interval: 1,
                },
                {
                    key: 'd1-1k-15',
                    class_name: 'D1-1C-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 15,
                    recurrence_interval: 2,
                },
                {
                    key: 'd1-1k-mo-11',
                    class_name: 'D1-1C-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 1,
                    hour: 11,
                    recurrence_interval: 2,
                },
                {
                    key: 'd1-1k-gos-14',
                    class_name: 'D1-1K-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 14,
                    recurrence_interval: 1,
                },
                {
                    key: 'd1-1k-gos-15',
                    class_name: 'D1-1K-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 15,
                    recurrence_interval: 2,
                },
            ],
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
            ],
            timeOptions: [
                { title: '11. Stunde', shortTitle: '11.', value: 11 },
                { title: '12. Stunde', shortTitle: '12.', value: 12 },
                { title: '14. Stunde', shortTitle: '14.', value: 14 },
                { title: '15. Stunde', shortTitle: '15.', value: 15 },
            ],
            schoolHours: [
                { hour: 11, from: '17:50:00', until: '18:35:00' },
                { hour: 12, from: '18:45:00', until: '19:30:00' },
                { hour: 14, from: '20:25:00', until: '21:10:00' },
                { hour: 15, from: '21:10:00', until: '21:55:00' },
            ],
        }

        const groups = methods.courseGroupItems.call(ctx, course)
        const fullGroup = groups.find(group => group.title === 'D1-1C-GOS')
        const halfLoadGroup = groups.find(group => group.title === 'D1-1K-GOS')

        expect(fullGroup.meta).toBe('Mo 11.-12., 17:50-18:35 (2-w), 18:45-19:30 (1-w), Di 14.-15., 20:25-21:10 (1-w), 21:10-21:55 (2-w)')
        expect(methods.courseGroupDistanceLearning.call(ctx, course, fullGroup)).toBe(false)
        expect(halfLoadGroup.meta).toBe('Di 14.-15., 20:25-21:10 (1-w), 21:10-21:55 (2-w)')
        expect(methods.courseGroupDistanceLearning.call(ctx, course, halfLoadGroup)).toBe(true)
    })

    it('applies mixed weekly recurrence formatting to expanded additional course group rows', () => {
        const methods = (RobotTimetable as any).methods
        const course = { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 3 }
        const ctx = {
            ...methods,
            additionalCourseSelectedKeys: ['D1'],
            deselectedCourseGroupKeys: ['D1|D1-1C-GOS'],
            studentAdditionalCourses: [course],
            configuredCourseGroups: [
                {
                    key: 'd1-1k-mo-12',
                    class_name: 'D1-1C-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 1,
                    hour: 12,
                    recurrence_interval: 1,
                },
                {
                    key: 'd1-1k-14',
                    class_name: 'D1-1C-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 14,
                    recurrence_interval: 1,
                },
                {
                    key: 'd1-1k-15',
                    class_name: 'D1-1C-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 15,
                    recurrence_interval: 2,
                },
                {
                    key: 'd1-1k-mo-11',
                    class_name: 'D1-1C-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 1,
                    hour: 11,
                    recurrence_interval: 2,
                },
                {
                    key: 'd1-1k-gos-14',
                    class_name: 'D1-1K-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 14,
                    recurrence_interval: 1,
                },
                {
                    key: 'd1-1k-gos-15',
                    class_name: 'D1-1K-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 2,
                    hour: 15,
                    recurrence_interval: 2,
                },
            ],
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
            ],
            timeOptions: [
                { title: '11. Stunde', shortTitle: '11.', value: 11 },
                { title: '12. Stunde', shortTitle: '12.', value: 12 },
                { title: '14. Stunde', shortTitle: '14.', value: 14 },
                { title: '15. Stunde', shortTitle: '15.', value: 15 },
            ],
            schoolHours: [
                { hour: 11, from: '17:50:00', until: '18:35:00' },
                { hour: 12, from: '18:45:00', until: '19:30:00' },
                { hour: 14, from: '20:25:00', until: '21:10:00' },
                { hour: 15, from: '21:10:00', until: '21:55:00' },
            ],
        }

        const groups = methods.courseGroupItems.call(ctx, course)
        const fullGroup = groups.find(group => group.title === 'D1-1C-GOS')
        const halfLoadGroup = groups.find(group => group.title === 'D1-1K-GOS')

        expect(fullGroup.meta).toBe('Mo 11.-12., 17:50-18:35 (2-w), 18:45-19:30 (1-w), Di 14.-15., 20:25-21:10 (1-w), 21:10-21:55 (2-w)')
        expect(methods.additionalCourseGroupSelected.call(ctx, course, fullGroup)).toBe(false)
        expect(methods.additionalCourseGroupSelected.call(ctx, course, halfLoadGroup)).toBe(true)
        expect(methods.additionalCoursePartiallySelected.call(ctx, course)).toBe(true)
        expect(halfLoadGroup.meta).toBe('Di 14.-15., 20:25-21:10 (1-w), 21:10-21:55 (2-w)')
        expect(methods.courseGroupDistanceLearning.call(ctx, course, halfLoadGroup)).toBe(true)
    })

    it('does not show timetable variants where a course is softly skipped', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'D7', code: 'D7', name: 'Deutsch 7', branch: 'alle', hours: 1 },
                { key: 'M7', code: 'M7', name: 'Mathematik 7', branch: 'alle', hours: 1 },
                { key: 'E7', code: 'E7', name: 'Englisch 7', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                { key: 'd7', class_name: 'D7-7C-DREI', course: 'D7', subject: 'D', weekday: 1, hour: 1 },
                { key: 'm7', class_name: 'M7-7C-MAL', course: 'M7', subject: 'M', weekday: 1, hour: 2 },
                { key: 'e7', class_name: 'E7-7C-RAI', course: 'E7', subject: 'E', weekday: 1, hour: 2 },
            ],
            constraints: {
                availableWeekdays: [1],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2],
            },
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
            ],
            timeOptions: [
                { title: '1. Stunde', shortTitle: '1. Stunde', value: 1 },
                { title: '2. Stunde', shortTitle: '2. Stunde', value: 2 },
            ],
            schoolHours: [
                { hour: 1, from: '17:50:00', until: '18:35:00' },
                { hour: 2, from: '18:45:00', until: '19:30:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables.length).toBeGreaterThan(0)
        expect(ctx.generatedTimetables.flatMap(timetable => timetable.problems).some(problem =>
            problem.includes('in dieser Variante nicht eingeplant'),
        )).toBe(false)
        expect(ctx.generationProblems.some(problem => problem.includes('in dieser Variante nicht eingeplant'))).toBe(false)
        expect(ctx.generatedTimetables.some(timetable =>
            Object.values(timetable.slots).some(slot => methods.generatedSlotConflicts.call(ctx, slot).length),
        )).toBe(true)
    })

    it('shows imported TT details as a normal second line for generated slots', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }

        expect(methods.generatedSlotDetails.call(ctx, {
            sourceLabel: 'INF1-Grp1-KRO',
            courseGroup: {
                teacher: 'KRO',
                rooms: ['EDV1', 'EDV2'],
            },
        })).toBe('INF1-Grp1-KRO · KRO · EDV1, EDV2')
    })

    it('keeps future courses available so prerequisites can make later modules bookable', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            subjectRows: [
                { semester: 3, code: 'M3' },
                { semester: 4, code: 'M4' },
                { semester: 5, code: 'M5' },
                { semester: 6, code: 'M6' },
            ],
            coursesForSemester(semester: number) {
                return this.subjectRows
                    .filter(subject => subject.semester === semester)
                    .map(subject => subject.code)
            },
        }

        expect(methods.coursesAfterSemester.call(ctx, 4)).toEqual(['M5', 'M6'])
    })

    it('uses planned first modules to allow second modules and blocks unplanned second modules', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                school_level: '09',
                attendance_year: '1',
            },
            selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            subjectMappings: [],
            studentCompletedCourses: [],
            subjectRows: [
                {
                    id: 1,
                    semester: 1,
                    branch: 'common',
                    json_code: 'D1',
                    json_subject: 'D',
                    name: 'Deutsch 1',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 2,
                    branch: 'common',
                    json_code: 'D2',
                    json_subject: 'D',
                    name: 'Deutsch 2',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 2,
                    branch: 'common',
                    json_code: 'GS1',
                    json_subject: 'GS',
                    name: 'Geschichte 1',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 4,
                    semester: 3,
                    branch: 'common',
                    json_code: 'GS2',
                    json_subject: 'GS',
                    name: 'Geschichte 2',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 5,
                    semester: 3,
                    branch: 'common',
                    json_code: 'D3',
                    json_subject: 'D',
                    name: 'Deutsch 3',
                    hours_per_week: 3,
                    is_active: true,
                },
            ],
        }

        expect(computed.studentPlannedCourses.call(ctx).map(course => course.code)).toEqual(['D1'])
        expect(computed.studentAdditionalCourses.call(ctx).map(course => course.code)).toEqual(['D2', 'GS1'])
    })

    it('does not show courses already listed in regular courses as additional courses', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                school_level: '09',
                attendance_year: '1',
            },
            selection: {
                semester: 2,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            subjectMappings: [],
            studentCompletedCourses: [],
            subjectRows: [
                {
                    id: 1,
                    semester: 1,
                    branch: 'common',
                    json_code: 'D1',
                    json_subject: 'D',
                    name: 'Deutsch 1',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 2,
                    branch: 'common',
                    json_code: 'D2',
                    json_subject: 'D',
                    name: 'Deutsch 2',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 2,
                    branch: 'common',
                    json_code: 'GS1',
                    json_subject: 'GS',
                    name: 'Geschichte 1',
                    hours_per_week: 3,
                    is_active: true,
                },
            ],
        }

        Object.defineProperty(ctx, 'availableCourses', {
            get() {
                return computed.availableCourses.call(this)
            },
        })

        expect(computed.availableCourses.call(ctx).map(course => course.code)).toEqual(['D1'])
        expect(computed.studentAdditionalCourses.call(ctx).map(course => course.code)).toEqual(['D2', 'GS1'])
    })

    it('shows second and third modules as additional after the first module is completed', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selectedStudent: {
                school_level: '09',
                attendance_year: '1',
            },
            selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            subjectMappings: [],
            studentCompletedCourses: [
                { subject: 'D1', grade: '3' },
            ],
            subjectRows: [
                {
                    id: 1,
                    semester: 1,
                    branch: 'common',
                    json_code: 'D1',
                    json_subject: 'D',
                    name: 'Deutsch 1',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 2,
                    branch: 'common',
                    json_code: 'D2',
                    json_subject: 'D',
                    name: 'Deutsch 2',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 3,
                    branch: 'common',
                    json_code: 'D3',
                    json_subject: 'D',
                    name: 'Deutsch 3',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 4,
                    semester: 4,
                    branch: 'common',
                    json_code: 'D4',
                    json_subject: 'D',
                    name: 'Deutsch 4',
                    hours_per_week: 3,
                    is_active: true,
                },
            ],
        }

        expect(computed.studentAdditionalCourses.call(ctx).map(course => course.code)).toEqual(['D2', 'D3'])
    })

    it('caches expanded course groups per course for checkbox state checks', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const course = { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 3 }
        const ctx = {
            ...methods,
            availableCourses: [course],
            studentAdditionalCourses: [course],
            configuredCourseGroups: [
                {
                    key: 'd1-1k-mo-12',
                    class_name: 'D1-1C-GOS',
                    course: 'D1',
                    subject: 'D',
                    weekday: 1,
                    hour: 12,
                },
            ],
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
            ],
            timeOptions: [
                { title: '12. Stunde', shortTitle: '12.', value: 12 },
            ],
            schoolHours: [
                { hour: 12, from: '18:45:00', until: '19:30:00' },
            ],
        }

        const courseGroupItemsByCourseKey = computed.courseGroupItemsByCourseKey.call(ctx)

        expect(courseGroupItemsByCourseKey.get('D1')).toHaveLength(1)
        expect(courseGroupItemsByCourseKey.get('D1')?.[0].title).toBe('D1-1C-GOS')

        const cachedContext = {
            ...ctx,
            configuredCourseGroups: [],
            courseGroupItemsByCourseKey,
        }

        expect(methods.courseGroupItems.call(cachedContext, course)).toBe(courseGroupItemsByCourseKey.get('D1'))
    })

    it('clears timetable counts without rebuilding additional course candidates', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            fullGreenTimetableCount: 4,
            greenTimetableCount: 3,
            conflictTimetableCount: 2,
            additionalCourseTimetableCount: 1,
            additionalCourseSelectedKeys: [],
            additionalCourseTimetableRequired: true,
            qualityCounters: [{ key: 'compact_days' }],
            allQualityCriteriaCount: 7,
            fullGreenTimetableCountError: 'Fehler',
            normalizeTimetableResultCounters: vi.fn(),
        }

        Object.defineProperty(ctx, 'selectedAdditionalCourses', {
            get() {
                throw new Error('selectedAdditionalCourses should not be read while clearing stale counts')
            },
        })

        methods.clearTimetableCountResults.call(ctx)

        expect(ctx.fullGreenTimetableCount).toBeNull()
        expect(ctx.greenTimetableCount).toBeNull()
        expect(ctx.conflictTimetableCount).toBeNull()
        expect(ctx.additionalCourseTimetableCount).toBeNull()
        expect(ctx.additionalCourseTimetableRequired).toBe(false)
        expect(ctx.qualityCounters).toEqual([])
        expect(ctx.allQualityCriteriaCount).toBeNull()
        expect(ctx.fullGreenTimetableCountError).toBe('')
        expect(ctx.normalizeTimetableResultCounters).toHaveBeenCalledOnce()
    })
})
