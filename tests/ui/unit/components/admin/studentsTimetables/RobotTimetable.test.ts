import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import RobotTimetable from '@/pages/admin/studentsTimetables/robot/RobotTimetable.vue'

describe('Students timetable robot page', () => {
    it('provides selectable robot timetable criteria', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/robot/RobotTimetable.vue',
            'utf8',
        )

        expect(componentSource).toContain('Roboter Stundenplan')
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
        expect(componentSource).toContain('Nicht möglich')
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
        expect(componentSource).toContain('selectedCourses()')
        expect(componentSource).toContain('selectedCoursesHours()')
        expect(componentSource).toContain('courseSelected(course)')
        expect(componentSource).toContain('courseFullySelected(course)')
        expect(componentSource).toContain('coursePartiallySelected(course)')
        expect(componentSource).toContain('setCourseSelected(course, selected)')
        expect(componentSource).toContain('courseGroupSelectionKeys(course)')
        expect(componentSource).toContain('selectAllCourses()')
        expect(componentSource).toContain('deselectAllCourses()')
        expect(componentSource).toContain('Alle auswählen')
        expect(componentSource).toContain('Alle abwählen')
        expect(componentSource).toContain('v-model="courseSelectionPanels"')
        expect(componentSource).toContain('v-model="courseItemPanels"')
        expect(componentSource).toContain('@update:model-value="setCourseSelected(course, $event)"')
        expect(componentSource).toContain('Stundenpläne erstellen')
        expect(componentSource).toContain('Volle grüne Stundenpläne')
        expect(componentSource).toContain('Grüne Stundenpläne')
        expect(componentSource).toContain('qualityCounters')
        expect(componentSource).toContain('quality_counters')
        expect(componentSource).toContain('Qualitätskriterien')
        expect(componentSource).toContain('qualityCounterCountLabel(counter)')
        expect(componentSource).toContain('qualityCounterDetail(counter)')
        expect(componentSource).toContain('<v-checkbox-btn')
        expect(componentSource).toContain('qualityCounterReached(counter)')
        expect(componentSource).toContain("selectedTimetableResultType: 'full_green'")
        expect(componentSource).toContain("selectedTimetableResultType === 'full_green'")
        expect(componentSource).toContain("selectedTimetableResultType === 'green'")
        expect(componentSource).toContain("setSelectedTimetableResultType('full_green', $event)")
        expect(componentSource).toContain("setSelectedTimetableResultType('green', $event)")
        expect(componentSource).toContain('setSelectedTimetableResultType(type, selected)')
        expect(componentSource).toContain('robot-count-card--selected')
        expect(componentSource).toContain('fullGreenTimetableNumber')
        expect(componentSource).toContain('greenTimetableNumber')
        expect(componentSource).toContain("v-if=\"selectedTimetableResultType === 'full_green' && fullGreenTimetableCount > 0\"")
        expect(componentSource).toContain("v-if=\"selectedTimetableResultType === 'green' && greenTimetableCount > 0\"")
        expect(componentSource).toContain('icon="mdi-chevron-left"')
        expect(componentSource).toContain('icon="mdi-chevron-right"')
        expect(componentSource).toContain("moveTimetableResultCounter('full_green', -1)")
        expect(componentSource).toContain("moveTimetableResultCounter('green', 1)")
        expect(componentSource).toContain('normalizeTimetableResultCounters()')
        expect(componentSource).toContain('robot-count-card__counter')
        expect(componentSource).toContain('selected_timetable_type: this.selectedTimetableResultType')
        expect(componentSource).toContain('selected_timetable_number: this.timetableResultCounter(this.selectedTimetableResultType)')
        expect(componentSource).toContain('backendTimetableFromResponse(response.data.data.selected_timetable)')
        expect(componentSource).toContain('selectedRobotTimetable()')
        expect(componentSource).toContain('robotTimetableSlot(weekday, time)')
        expect(componentSource).toContain('robotTimetableCellClasses(weekday, time)')
        expect(componentSource).toContain('robotTimetableOccasionalMarkers(weekday, time)')
        expect(componentSource).toContain('selectedOccasionalAppointmentGroupsForTimetable(timetable)')
        expect(componentSource).toContain('@click="loadFullGreenTimetableCount"')
        expect(componentSource).toContain("axios.post('/api/admin/students-timetables/robot/full-green-count'")
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
        expect(componentSource).toContain('<div class="robot-course-list__title">Stundenplan</div>')
        expect(componentSource).toContain('robot-generated-grid')
        expect(componentSource).toContain('robot-generated-cell__details')
        expect(componentSource).toContain('generatedSlotDetails(slot)')
        expect(componentSource).toContain('robot-generated-cell--conflict')
        expect(componentSource).toContain('robot-generated-cell__conflicts')
        expect(componentSource).toContain('generatedSlotConflicts(slot)')
        expect(componentSource).toContain('generatedSlotConflictBlocks(slot)')
        expect(componentSource).toContain('generatedCellOccasionalMarkers(timetable, weekday, time)')
        expect(componentSource).toContain('robot-generated-cell__occasional-marker')
        expect(componentSource).toContain('robot-generated-appointments')
        expect(componentSource).toContain('displayedTimetableProblems(timetable)')
        expect(componentSource).toContain('problemIsCoveredByOccasionalAppointments(problem)')
        expect(componentSource).toContain('groupedOccasionalAppointments(timetable)')
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
            'Gymnasialer Zweig',
            'BE - Bildnerische Erziehung',
            'F - Französisch',
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
            subjectMappings: [
                { json_subject: 'GW', tt_subject: 'GWB', is_active: true },
                { json_subject: 'LPT', tt_subject: 'LET', is_active: true },
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
        expect(computed.generatedTimes.call(ctx).map(time => time.value)).toEqual([7, 14])
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

    it('renders conflict cells as both conflicting course blocks', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }
        const blocks = methods.generatedSlotConflictBlocks.call(ctx, {
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
        })

        expect(blocks.map(block => block.code)).toEqual(['ÖKO2', 'INF3'])
        expect(blocks.map(block => methods.generatedSlotDetails.call(ctx, block))).toEqual([
            'ÖKO2- BIE',
            'INF3-8AB-MAY',
        ])
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

    it('can select and deselect courses for timetable generation', () => {
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
            clearGeneratedTimetables() {},
            saveLastRobotState() {},
        }

        expect(computed.selectedCourses.call(ctx).map(course => course.code)).toEqual(['INF1'])

        methods.setCourseSelected.call(ctx, ctx.availableCourses[1], true)
        expect(ctx.deselectedCourseKeys).toEqual([])
        expect(computed.selectedCourses.call(ctx).map(course => course.code)).toEqual(['INF1', 'GW1'])

        methods.deselectAllCourses.call(ctx)
        expect(computed.selectedCourses.call(ctx)).toEqual([])
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
                    branch: 'common',
                    json_code: 'ME1',
                    json_subject: 'ME',
                    name: 'Musikerziehung 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 6,
                    semester: 1,
                    branch: 'common',
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

        expect(courseCodes()).toEqual(['D1', 'ETH1', 'L1', 'ME1', 'WIK1'])

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

    it('does not rank a replaced same-slot course as scheduled', () => {
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
            .map(slot => slot.code)
            .sort()

        expect(scheduledCodes).toEqual(['E4', 'M4'])
        expect(ctx.generatedTimetables[0].slots['6-4'].code).toBe('E4')
        expect(ctx.generatedTimetables[0].slots['5-4'].code).toBe('M4')
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
})
