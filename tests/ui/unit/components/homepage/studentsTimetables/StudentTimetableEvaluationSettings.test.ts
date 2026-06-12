import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import StudentTimetableEvaluationSettings from '@/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue'
import Overview from '@/pages/homepage/studentsTimetables/overview/Overview.vue'

describe('Student timetable evaluation settings', () => {
    it('receives proposed courses from the student overview', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain(':proposed-courses="automaticTimetableSelectableCourses"')
        expect(source).toContain(':initial-step="automaticTimetableStep"')
        expect(source).toContain(':initial-selected-course-keys="automaticTimetableCourseKeys"')
        expect(source).toContain(':initial-selected-quality-criterion-keys="automaticTimetableQualityCriterionKeys"')
        expect(source).toContain(':default-quality-criterion-selection="!automaticTimetableQualityCriteriaSelectionExplicit"')
        expect(source).toContain(':course-sections="automaticTimetableAllCourseSections"')
        expect(source).toContain('automaticTimetableAllCourseSections()')
        expect(source).toContain("String(section?.key || '') === 'additional'")
        expect(source).toContain('this.overview?.additional_courses')
        expect(source).toContain("title: 'Zusätzliche Kurse'")
        expect(source).toContain('@course-selection-change="setAutomaticTimetableCourseKeys"')
        expect(source).toContain('@courses-selected="finishAutomaticTimetable"')
        expect(source).toContain('@quality-criteria-selection-change="setAutomaticTimetableQualityCriterionKeys"')
        expect(source).toContain('@step-change="setAutomaticTimetableStep"')
        expect(source).toContain('automatic_timetable')
        expect(source).toContain('automatic_timetable_courses')
        expect(source).toContain('automatic_timetable_criteria')
        expect(source).toContain("const noAutomaticTimetableQualityCriteriaValue = '__none'")
        expect(source).toContain("this.setAutomaticTimetableStep('criteria')")
        expect(source).toContain("'criteria', 'courses', 'result'")
    })

    it('opens a manual timetable from the student overview', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain('@click="openManualTimetable"')
        expect(source).toContain('showManualTimetable')
        expect(source).toContain('manual_timetable')
        expect(source).toContain('manualTimetableSelection()')
        expect(source).toContain('manualSelectedCourseGroups()')
        expect(source).toContain('manualGroupsForCell(weekday.value, hour.value)')
        expect(source).toContain('<v-checkbox-btn')
        expect(source).toContain('Manueller Stundenplan')
        expect(source).toContain('Keine passenden Kurstermine gefunden.')
        expect(source).toContain('showManualTimetable && courseSections.length')
        expect(source).toContain('class="manual-overview-course-card"')
        expect(source).toContain('v-model="expandedManualOverviewCoursePanels"')
        expect(source).toContain('expandedManualOverviewCoursePanels: []')
        expect(source).toContain('<h3>Kurse</h3>')
        expect(source).toContain('courseSectionTotalCount')
    })

    it('builds manual timetable cells from selected student courses', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const courseGroup = {
            key: 'group-1',
            course: 'D1',
            display_label: 'D1 - 4A - MUE',
            weekday: 1,
            hour: 11,
            time_from: '16:10',
            time_until: '16:55',
            teacher: 'MUE',
            rooms: ['101'],
        }
        const course = {
            key: 'course-1',
            code: 'D1',
            name: 'Deutsch',
            course_groups: [courseGroup],
        }
        const ctx = {
            manualSelectedCourseKeys: ['course-1'],
            overview: {
                school_hours: [],
                manual_timetable: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [course],
                        },
                    ],
                },
            },
            manualCourseKey: methods.manualCourseKey,
            manualCourseGroups: methods.manualCourseGroups,
            manualCourseGroupKey: methods.manualCourseGroupKey,
            manualTimetableHourTimeFrom: methods.manualTimetableHourTimeFrom,
            manualTimetableHourTimeUntil: methods.manualTimetableHourTimeUntil,
            configuredSchoolHour: methods.configuredSchoolHour,
            formatTimeValue: methods.formatTimeValue,
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
            get manualSelectedCourses() {
                return computed.manualSelectedCourses.call(ctx)
            },
            get manualSelectedCourseGroups() {
                return computed.manualSelectedCourseGroups.call(ctx)
            },
        }

        expect(computed.manualSelectedCourseGroups.call(ctx)).toEqual([courseGroup])
        expect(computed.manualTimetableHours.call(ctx)).toEqual([
            {
                value: 11,
                hourLabel: '11.',
                timeFrom: '16:10',
                timeUntil: '16:55',
            },
        ])
        expect(methods.manualGroupsForCell.call(ctx, 1, 11)).toEqual([courseGroup])
        expect(methods.manualCourseGroupDetails(courseGroup)).toBe('D1 - 4A - MUE · MUE · 101')
    })

    it('shows the imported student religion on the religion card', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain('class="overview-selected-card__meta"')
        expect(source).toContain('this.overview?.selection_items')
        expect(source).toContain('this.overview?.course_sections')
        expect(source).toContain('this.overview?.selection_options?.[optionKey]')
        expect(source).toContain('icon="mdi-pencil"')
        expect(source).toContain('@click="openSelectionDialog(item)"')
        expect(source).toContain('restoreSelectionDefaults')
        expect(source).toContain('selectionOverridePayload()')
        expect(source).toContain('this.studentTimetablesStore.updateProfileSelection')
        expect(source).toContain('this.studentTimetablesStore.restoreProfileSelection')
        expect(source).toContain('this.overview?.selection_override')
        expect(source).not.toContain('localStorage')
        expect(source).not.toContain('studentReligionMeta(religion)')
    })

    it('uses a back action instead of a reset action', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('prepend-icon="mdi-arrow-left"')
        expect(source).toContain('@click="handleBack"')
        expect(source).toContain("this.$emit('close')")
        expect(source).toContain('Zurück')
        expect(source).toContain('student-evaluation-settings__close-button')
        expect(source).toContain('prepend-icon="mdi-arrow-left"')
        expect(source).toContain('student-result-course-panels')
        expect(source).toContain('v-if="currentStep === \'courses\'"')
        expect(source).toContain('v-else-if="currentStep === \'result\'" class="student-generated-timetable"')
        expect(source).toContain('student-generated-timetable__back-button')
        expect(source).toContain('student-evaluation-settings__automatic-card')
        expect(source).toContain('color="primary"')
        expect(source).toContain('color="success"')
        expect(source).toContain('readOnlySelectedCourseSections')
        expect(source).toContain('v-for="section in regularCourseSections"')
        expect(source).toContain('additionalCoursePanelVisible')
        expect(source).toContain('.student-evaluation-settings__course-section-card--additional')
        expect(source).toContain('background: #fed7aa')
        expect(source).toContain('border-color: rgba(234, 88, 12, 0.28)')
        expect(source).toContain('setAdditionalCourseSelected(course, $event)')
        expect(source).toContain(':disabled="additionalCourseSelectionLocked || generatingTimetable"')
        expect(source).toContain('Stundenplan erweitern')
        expect(source).toContain('createExtendedAutomaticTimetable')
        expect(source).toContain(':model-value="true"')
        expect(source).toContain('student-generated-criteria__check')
        expect(source).toContain('student-generated-criteria__status')
        expect(source).toContain("'student-generated-criteria__card--reached': generatedQualityCriterionSelected(criterion) && generatedQualityCriterionReached(criterion)")
        expect(source).toContain("generatedQualityCriterionReached(criterion) ? 'mdi-check-circle' : 'mdi-close-circle'")
        expect(source).toContain('<v-checkbox-btn')
        expect(source).toContain(':model-value="generatedQualityCriterionSelected(criterion)"')
        expect(source).toContain('@update:model-value="toggleGeneratedQualityCriterion(criterion)"')
        expect(source).toContain('disabled')
        expect(source).not.toContain('@click="resetChanges"')
    })

    it('initializes selected course keys from the url state', () => {
        const data = (StudentTimetableEvaluationSettings as any).data
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            initialStep: 'courses',
            initialSelectedCourseKeys: ['course-2'],
            initialSelectedQualityCriterionKeys: ['saturday_free'],
            normalizedStep: methods.normalizedStep,
            normalizedCourseKeys: methods.normalizedCourseKeys,
            normalizedResultBackStep: methods.normalizedResultBackStep,
        }

        expect(data.call(ctx).selectedCourseKeys).toEqual(['course-2'])
        expect(data.call(ctx).selectedQualityCriterionKeys).toEqual(['saturday_free'])
        expect(data.call(ctx).resultBackStep).toBe('courses')
    })

    it('moves from criteria to the result while keeping criteria as the back target', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const proposedCourses = [
            { key: 'course-1', code: 'M1', name: 'Mathematik', semester: 1, hours: 3 },
            { key: 'course-2', code: 'D1', name: 'Deutsch', semester: 1, hours: 2 },
        ]
        const ctx = {
            currentStep: 'criteria',
            proposedCourses,
            selectedCourseKeys: [],
            saveSettings: async () => true,
            ensureDefaultQualityCriterionSelection: () => undefined,
            resetGeneratedTimetableSelection: () => undefined,
            courseSelectionKey: methods.courseSelectionKey,
            courseSelected: methods.courseSelected,
            moveToStep: methods.moveToStep,
            normalizedStep: methods.normalizedStep,
            normalizedResultBackStep: methods.normalizedResultBackStep,
            selectAllProposedCourses: methods.selectAllProposedCourses,
            emitCourseSelectionChange: methods.emitCourseSelectionChange,
            ensureAutomaticTimetableForResultStep: () => undefined,
            resultBackStep: 'criteria',
            get selectedCourses() {
                return proposedCourses.filter(course => methods.courseSelected.call(ctx, course))
            },
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        await methods.continueToNextStep.call(ctx)

        expect(ctx.currentStep).toBe('result')
        expect(ctx.resultBackStep).toBe('criteria')
        expect(ctx.selectedCourseKeys).toEqual(['course-1', 'course-2'])

        methods.setCourseSelected.call(ctx, proposedCourses[1], false)

        expect(ctx.selectedCourseKeys).toEqual(['course-1', 'course-2'])

        expect(emitted).toEqual([
            {
                event: 'course-selection-change',
                payload: ['course-1', 'course-2'],
            },
            {
                event: 'step-change',
                payload: 'result',
            },
        ])
    })

    it('creates an automatic timetable before showing the result step', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'courses',
            selectedCourseKeys: ['course-1'],
            selectedCourses: [{ key: 'course-1' }],
            resultBackStep: 'criteria',
            resetGeneratedTimetableSelection: () => undefined,
            createAutomaticTimetable: async () => {
                ctx.currentStep = 'result'
            },
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        await methods.continueToNextStep.call(ctx)

        expect(ctx.currentStep).toBe('result')
        expect(ctx.resultBackStep).toBe('courses')
        expect(emitted).toEqual([])

        await methods.continueToNextStep.call(ctx)

        expect(emitted).toEqual([
            {
                event: 'courses-selected',
                payload: [{ key: 'course-1' }],
            },
        ])
    })

    it('returns from result to the step that opened it', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'result',
            resultBackStep: 'criteria',
            selectedCourseKeys: [],
            normalizedStep: methods.normalizedStep,
            normalizedResultBackStep: methods.normalizedResultBackStep,
            moveToStep: methods.moveToStep,
            selectAllProposedCourses: () => undefined,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        methods.handleBack.call(ctx)

        expect(ctx.currentStep).toBe('criteria')
        expect(emitted).toEqual([
            {
                event: 'step-change',
                payload: 'criteria',
            },
        ])

        ctx.currentStep = 'result'
        ctx.resultBackStep = 'courses'

        methods.handleBack.call(ctx)

        expect(ctx.currentStep).toBe('courses')
        expect(emitted.at(-1)).toEqual({
            event: 'step-change',
            payload: 'courses',
        })
    })

    it('shows selected regular courses as read-only rows in the result step', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const selectedCourse = { key: 'course-1', code: 'M1' }
        const unselectedCourse = { key: 'course-2', code: 'D1' }
        const additionalCourse = { key: 'course-3', code: 'E2' }
        const ctx = {
            selectedCourseKeys: ['course-1'],
            courseSections: [
                {
                    key: 'missing',
                    title: 'Fehlende Kurse',
                    items: [selectedCourse, unselectedCourse],
                },
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [additionalCourse],
                },
            ],
            courseSelectionKey: methods.courseSelectionKey,
            courseSelectedByDefault: methods.courseSelectedByDefault,
            courseSelected: methods.courseSelected,
            get allCoursesSelectedByDefault() {
                return computed.allCoursesSelectedByDefault.call(ctx)
            },
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get regularCourseSections() {
                return computed.regularCourseSections.call(ctx)
            },
        }

        expect(computed.readOnlySelectedCourseSections.call(ctx)).toEqual([
            {
                key: 'missing',
                title: 'Fehlende Kurse',
                items: [selectedCourse],
            },
        ])
    })

    it('shows additional courses as a selectable result panel after timetable creation', () => {
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            courseSections: [
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [{ key: 'course-3', code: 'E2' }],
                },
            ],
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
            get readOnlySelectedCourseSections() {
                return []
            },
            get additionalCoursePanelVisible() {
                return computed.additionalCoursePanelVisible.call(ctx)
            },
        }

        expect(computed.additionalCoursePanelVisible.call(ctx)).toBe(true)
        expect(computed.resultCoursePanelsVisible.call(ctx)).toBe(true)
        expect(computed.additionalCourses.call(ctx)).toEqual([{ key: 'course-3', code: 'E2' }])
    })

    it('keeps additional courses unchecked when the generated timetable is first shown', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const course = { key: 'course-3', code: 'E2' }
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: [],
            courseSections: [
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [course],
                },
            ],
            courseSelectionKey: methods.courseSelectionKey,
            additionalCourseSelected: methods.additionalCourseSelected,
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
        }

        expect(computed.additionalCoursePanelVisible.call(ctx)).toBe(true)
        expect(methods.additionalCourseSelected.call(ctx, course)).toBe(false)
    })

    it('hides the generated timetable while additional course selection is pending and restores it on back', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const moveToStep = vi.fn()
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: [],
            additionalCourseSelectionChangedAfterTimetable: false,
            courseSelectionKey: methods.courseSelectionKey,
            commitAdditionalCourseSelectionChange: methods.commitAdditionalCourseSelectionChange,
            resetAdditionalCourseSelection: methods.resetAdditionalCourseSelection,
            moveToStep,
        }

        methods.setAdditionalCourseSelected.call(ctx, { key: 'course-3' }, true)

        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(true)

        methods.handleBack.call(ctx)

        expect(ctx.selectedAdditionalCourseKeys).toEqual([])
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
        expect(ctx.generatedTimetable).toEqual({ slots: {} })
        expect(moveToStep).not.toHaveBeenCalled()
    })

    it('locks additional course checkboxes after extending and unlocks them on back', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const moveToStep = vi.fn()
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: ['course-3'],
            additionalCourseSelectionChangedAfterTimetable: true,
            additionalCourseSelectionLocked: false,
            selectedTimetableType: 'green',
            selectedTimetableNumber: 3,
            courseSelectionKey: methods.courseSelectionKey,
            commitAdditionalCourseSelectionChange: methods.commitAdditionalCourseSelectionChange,
            resetGeneratedTimetableSelection: methods.resetGeneratedTimetableSelection,
            moveToStep,
            async createAutomaticTimetable() {
                this.generatedTimetable = { slots: { extended: true } }
                this.additionalCourseSelectionChangedAfterTimetable = false
            },
        }

        await methods.createExtendedAutomaticTimetable.call(ctx)

        expect(ctx.additionalCourseSelectionLocked).toBe(true)
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])
        expect(ctx.selectedTimetableType).toBe(null)
        expect(ctx.selectedTimetableNumber).toBe(1)

        methods.setAdditionalCourseSelected.call(ctx, { key: 'course-3' }, false)

        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])

        methods.handleBack.call(ctx)

        expect(ctx.additionalCourseSelectionLocked).toBe(false)
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(true)
        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])
        expect(moveToStep).not.toHaveBeenCalled()

        methods.setAdditionalCourseSelected.call(ctx, { key: 'course-3' }, false)

        expect(ctx.selectedAdditionalCourseKeys).toEqual([])
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
    })

    it('does not change course selection from the result read-only table', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'result',
            selectedCourseKeys: ['course-1'],
            courseSelectionKey: methods.courseSelectionKey,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        methods.setCourseSelected.call(ctx, { key: 'course-1' }, false)

        expect(ctx.selectedCourseKeys).toEqual(['course-1'])
        expect(emitted).toEqual([])
    })

    it('posts selected course keys to the student automatic timetable endpoint', async () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("axios.post('/api/homepage/students-timetables/automatic-timetable'")
        expect(source).toContain('selected_course_keys: this.selectedCourseKeys')
        expect(source).toContain('selected_additional_course_keys: this.selectedAdditionalCourseKeys')
        expect(source).toContain('selected_additional_courses_required: this.selectedAdditionalCourseKeys.length > 0')
        expect(source).toContain('selected_quality_criterion_keys: this.selectedQualityCriterionKeys')
        expect(source).toContain('selected_timetable_type: this.selectedTimetableType')
        expect(source).toContain('selected_timetable_number: this.selectedTimetableNumber')
        expect(source).toContain('selection: this.selectionOverride')
        expect(source).toContain('this.schoolHours = response.data?.data?.school_hours || []')
        expect(source).toContain('selectionOverride')
        expect(source).toContain("this.moveToStep('result')")
        expect(source).toContain('ensureAutomaticTimetableForResultStep()')
        expect(source).toContain('canMoveGeneratedTimetable(-1)')
        expect(source).toContain('canMoveGeneratedTimetable(1)')
        expect(source).toContain('generatedTimetableDisplaySlot(weekday.value, hour.value)')
        expect(source).toContain('generatedTimetableOccasionalMarkers(weekday.value, hour.value)')
        expect(source).toContain('student-generated-timetable__time-range')
        expect(source).toContain('student-generated-timetable__badge')
        expect(source).toContain('generatedSlotWeekMarker(block)')
        expect(source).toContain("'student-generated-timetable__cell--additional': generatedTimetableCellHasAdditionalCourse(weekday.value, hour.value)")
        expect(source).toContain('generatedTimetableCellHasAdditionalCourse(weekday, hour)')
        expect(source).toContain('.student-generated-timetable__cell--additional')
        expect(source).toContain('student-generated-criteria__meta-row')
        expect(source).toContain('student-generated-criteria__label')
        expect(source).toContain('student-generated-criteria__controls')
        expect(source).toContain('activeGeneratedQualityCriteria.length || selectedAdditionalCourseKeys.length')
        expect(source).toContain('student-generated-criteria__card--additional')
        expect(source).toContain('Zusatzkurse')
        expect(source).toContain('generatedAdditionalCourseAcceptanceLabel()')
        expect(source).toContain('generatedAdditionalCourseAcceptanceIcon()')
        expect(source).toContain('Stundenpläne')
    })

    it('shows current timetable criterion status independently from the checkbox selection', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods

        expect(methods.generatedQualityCriterionReached({ selected_reached: true, count: 0 })).toBe(true)
        expect(methods.generatedQualityCriterionReached({ selected_reached: false, count: 8 })).toBe(false)
        expect(methods.generatedQualityCriterionReached({ count: 3 })).toBe(true)
    })

    it('summarizes selected additional courses next to generated criteria', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            selectedAdditionalCourseKeys: ['course-3', 'course-4'],
            generatedTimetable: {
                additionalCoursesAccepted: false,
                acceptedAdditionalCourseCount: 1,
                missingAdditionalCourses: [{ key: 'course-4' }],
            },
            generatedAdditionalCoursesAccepted: methods.generatedAdditionalCoursesAccepted,
            generatedMissingAdditionalCourses: methods.generatedMissingAdditionalCourses,
            generatedAcceptedAdditionalCourseCount: methods.generatedAcceptedAdditionalCourseCount,
        }

        expect(methods.generatedAdditionalCourseAcceptanceLabel.call(ctx)).toBe('1 / 2')
        expect(methods.generatedAdditionalCourseAcceptanceIcon.call(ctx)).toBe('mdi-alert-circle')
        expect(methods.generatedAdditionalCourseAcceptanceColor.call(ctx)).toBe('error')

        ctx.generatedTimetable = {
            additionalCoursesAccepted: true,
            acceptedAdditionalCourseCount: 2,
            missingAdditionalCourses: [],
        }

        expect(methods.generatedAdditionalCourseAcceptanceLabel.call(ctx)).toBe('2 / 2')
        expect(methods.generatedAdditionalCourseAcceptanceIcon.call(ctx)).toBe('mdi-check-circle')
        expect(methods.generatedAdditionalCourseAcceptanceColor.call(ctx)).toBe('success')
    })

    it('toggles generated quality criteria and emits url state', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'result',
            generatingTimetable: false,
            selectedCourseKeys: ['course-1'],
            selectedQualityCriterionKeys: [],
            emitQualityCriteriaSelectionChange: methods.emitQualityCriteriaSelectionChange,
            resetGeneratedTimetableSelection: methods.resetGeneratedTimetableSelection,
            selectedTimetableType: 'green',
            selectedTimetableNumber: 3,
            createAutomaticTimetable: async () => undefined,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        await methods.toggleGeneratedQualityCriterion.call(ctx, { key: 'saturday_free' })

        expect(ctx.selectedQualityCriterionKeys).toEqual(['saturday_free'])
        expect(emitted).toEqual([
            {
                event: 'quality-criteria-selection-change',
                payload: ['saturday_free'],
            },
        ])

        await methods.toggleGeneratedQualityCriterion.call(ctx, { key: 'saturday_free' })

        expect(ctx.selectedQualityCriterionKeys).toEqual([])
        expect(emitted).toEqual([
            {
                event: 'quality-criteria-selection-change',
                payload: ['saturday_free'],
            },
            {
                event: 'quality-criteria-selection-change',
                payload: [],
            },
        ])
    })

    it('defaults the first active quality criterion only when url state is not explicit', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const defaultContext = {
            defaultQualityCriterionSelection: true,
            defaultQualityCriterionSelectionApplied: false,
            selectedQualityCriterionKeys: [],
            criteria: [
                { key: 'saturday_free', enabled: true },
                { key: 'early_end', enabled: true },
            ],
            activeQualityCriterionKeys: methods.activeQualityCriterionKeys,
            emitQualityCriteriaSelectionChange: methods.emitQualityCriteriaSelectionChange,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        methods.ensureDefaultQualityCriterionSelection.call(defaultContext)

        expect(defaultContext.selectedQualityCriterionKeys).toEqual(['saturday_free'])

        const explicitEmptyContext = {
            ...defaultContext,
            defaultQualityCriterionSelection: false,
            defaultQualityCriterionSelectionApplied: false,
            selectedQualityCriterionKeys: [],
        }

        methods.ensureDefaultQualityCriterionSelection.call(explicitEmptyContext)

        expect(explicitEmptyContext.selectedQualityCriterionKeys).toEqual([])
    })

    it('moves within the current generated timetable result bucket with arrow controls', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            generatedTimetable: { type: 'green' },
            selectedTimetableType: 'green',
            selectedTimetableNumber: 2,
            timetableCounts: {
                full_green_timetable_count: 0,
                green_timetable_count: 2,
                conflict_timetable_count: 1,
            },
            generatedTimetableTypeOrder: methods.generatedTimetableTypeOrder,
            generatedTimetableCountForType: methods.generatedTimetableCountForType,
            resolveGeneratedTimetableSelection: methods.resolveGeneratedTimetableSelection,
            get generatedTimetableNavigationType() {
                return computed.generatedTimetableNavigationType.call(ctx)
            },
            get generatedTimetableTotalCount() {
                return computed.generatedTimetableTotalCount.call(ctx)
            },
            get generatedTimetableAbsoluteNumber() {
                return computed.generatedTimetableAbsoluteNumber.call(ctx)
            },
            canMoveGeneratedTimetable: methods.canMoveGeneratedTimetable,
            createAutomaticTimetable: async () => undefined,
        }

        expect(computed.generatedTimetablePositionLabel.call(ctx)).toBe('2 / 2')
        expect(methods.canMoveGeneratedTimetable.call(ctx, 1)).toBe(false)

        await methods.moveGeneratedTimetable.call(ctx, -1)

        expect(ctx.selectedTimetableType).toBe('green')
        expect(ctx.selectedTimetableNumber).toBe(1)
    })

    it('uses the selected criteria timetable count for the generated timetable counter', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            generatedTimetable: { type: 'green' },
            selectedTimetableType: 'green',
            selectedTimetableNumber: 1,
            selectedQualityCriterionKeys: ['saturday_free'],
            timetableCounts: {
                green_timetable_count: 12,
                selected_quality_criteria_count: 5,
            },
            selectedQualityCriteriaTimetableCount: methods.selectedQualityCriteriaTimetableCount,
            generatedTimetableCountForType: methods.generatedTimetableCountForType,
            get generatedTimetableNavigationType() {
                return computed.generatedTimetableNavigationType.call(ctx)
            },
            get generatedTimetableTotalCount() {
                return computed.generatedTimetableTotalCount.call(ctx)
            },
            get generatedTimetableAbsoluteNumber() {
                return computed.generatedTimetableAbsoluteNumber.call(ctx)
            },
        }

        expect(computed.generatedTimetablePositionLabel.call(ctx)).toBe('1 / 5')
    })

    it('renders single date overlaps as markers instead of visual conflicts', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            generatedTimetable: {
                slots: {},
            },
            generatedSlotBlock: methods.generatedSlotBlock,
            generatedSlotBlockIdentity: methods.generatedSlotBlockIdentity,
            generatedSlotBlockIsOccasional: methods.generatedSlotBlockIsOccasional,
            generatedSlotBlocksMatch: methods.generatedSlotBlocksMatch,
            generatedSlotConflictBlocksIncludingRegular: methods.generatedSlotConflictBlocksIncludingRegular,
            generatedSlotDetails: methods.generatedSlotDetails,
            generatedSlotWeekMarker: () => '',
            generatedSlotVisualConflictBlocks: methods.generatedSlotVisualConflictBlocks,
            generatedTimetableSlot: methods.generatedTimetableSlot,
            courseGroupDates: methods.courseGroupDates,
            uniqueGeneratedSlotBlocks: methods.uniqueGeneratedSlotBlocks,
            uniqueGeneratedTimetableOccasionalMarkers: methods.uniqueGeneratedTimetableOccasionalMarkers,
        }
        const singleDateGroup = {
            recurrence_type: 'single',
            dates_count: 1,
            dates: ['2026-02-17'],
            display_label: 'LPT - 1CK - DREI',
        }
        const weeklyGroup = {
            recurrence_type: 'weekly',
            dates_count: 20,
            dates: ['2026-02-17', '2026-02-24'],
            display_label: 'D1 - 1C - GOS',
        }
        const regularSlot = {
            code: 'D1',
            sourceLabel: 'D1-1C-GOS',
            courseGroup: weeklyGroup,
            conflicts: [
                {
                    code: 'LPT',
                    sourceLabel: 'LPT-1CK-DREI',
                    courseGroup: singleDateGroup,
                    isOccasional: true,
                },
            ],
        }
        const occasionalSlot = {
            code: 'LPT',
            sourceLabel: 'LPT-1CK-DREI',
            courseGroup: singleDateGroup,
            isOccasional: true,
            conflicts: [
                {
                    code: 'M1',
                    sourceLabel: 'M1-1C-MAY',
                    courseGroup: weeklyGroup,
                },
            ],
        }

        ctx.generatedTimetable.slots = {
            '3-14': occasionalSlot,
        }

        expect(methods.generatedSlotVisualConflictBlocks.call(ctx, regularSlot)).toEqual([])
        expect(methods.generatedSlotOccasionalConflictMarkers.call(ctx, regularSlot)).toMatchObject([
            {
                code: 'LPT',
                date: '2026-02-17',
            },
        ])
        expect(methods.generatedTimetableDisplaySlot.call(ctx, 3, 14)).toMatchObject({
            code: 'M1',
        })
    })

    it('shows hour times and generated slot badges in the timetable result', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            generatedTimetable: {
                slots: {
                    '1-5': {
                        key: 'slot-1',
                        code: 'M6',
                        sourceLabel: 'M6A',
                        isDistanceLearningCourse: true,
                        courseGroup: {
                            hour: 5,
                            time_from: '11:10:00',
                            time_until: '11:55:00',
                            recurrence_interval: 2,
                        },
                    },
                },
            },
            generatedTimetableSchoolHour: methods.generatedTimetableSchoolHour,
            generatedTimetableConfiguredSchoolHour: methods.generatedTimetableConfiguredSchoolHour,
            generatedTimetableHourTimeFrom: methods.generatedTimetableHourTimeFrom,
            generatedTimetableHourTimeUntil: methods.generatedTimetableHourTimeUntil,
            formatTimeValue: methods.formatTimeValue,
            courseGroupWeekMarkerValue: methods.courseGroupWeekMarkerValue,
            courseGroupWeekInterval: methods.courseGroupWeekInterval,
            courseGroupDates: methods.courseGroupDates,
            weekIntervalFromDates: methods.weekIntervalFromDates,
            dateFromIsoValue: methods.dateFromIsoValue,
            generatedSlotWeekMarker: methods.generatedSlotWeekMarker,
            visibleTimetableEntryWeekMarker: methods.visibleTimetableEntryWeekMarker,
            timetableEntryWeekMarkers: methods.timetableEntryWeekMarkers,
            timetableWeekMarkerEntries: methods.timetableWeekMarkerEntries,
            timetableEntriesShareWeekMarkerContext: methods.timetableEntriesShareWeekMarkerContext,
            timetableEntryCourseContextKey: methods.timetableEntryCourseContextKey,
        }

        expect(computed.generatedTimetableHours.call(ctx)).toEqual([
            {
                value: 5,
                hourLabel: '5.',
                timeFrom: '11:10',
                timeUntil: '11:55',
            },
        ])
        expect(methods.generatedSlotWeekMarker.call(ctx, ctx.generatedTimetable.slots['1-5'])).toBe('2-w')
        expect(ctx.generatedTimetable.slots['1-5'].isDistanceLearningCourse).toBe(true)
    })

    it('marks generated timetable cells with additional courses', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            generatedTimetable: {
                slots: {
                    '1-5': {
                        key: 'slot-1',
                        code: 'INF2',
                        isAdditionalCourse: true,
                    },
                },
                occasionalAppointments: [
                    {
                        key: 'appointment-1',
                        weekday: 2,
                        hour: 6,
                        code: 'INF2',
                        isAdditionalCourse: true,
                    },
                ],
            },
            generatedTimetableDisplaySlot: methods.generatedTimetableDisplaySlot,
            generatedTimetableSlot: methods.generatedTimetableSlot,
            generatedSlotBlock: methods.generatedSlotBlock,
            generatedSlotDisplayBlocks: methods.generatedSlotDisplayBlocks,
            generatedSlotVisualConflictBlocks: methods.generatedSlotVisualConflictBlocks,
            generatedSlotConflictBlocksIncludingRegular: methods.generatedSlotConflictBlocksIncludingRegular,
            generatedSlotBlockIsOccasional: methods.generatedSlotBlockIsOccasional,
            generatedSlotBlocks: methods.generatedSlotBlocks,
            generatedSlotBlockIdentity: methods.generatedSlotBlockIdentity,
            generatedSlotBlocksMatch: methods.generatedSlotBlocksMatch,
            uniqueGeneratedSlotBlocks: methods.uniqueGeneratedSlotBlocks,
            generatedSlotDetails: methods.generatedSlotDetails,
            generatedTimetableOccasionalMarkers: methods.generatedTimetableOccasionalMarkers,
            occasionalAppointmentSelectedForGeneratedTimetable: methods.occasionalAppointmentSelectedForGeneratedTimetable,
            courseGroupDates: () => [],
            generatedAppointmentWeekMarker: () => '',
            generatedSlotOccasionalConflictMarkers: () => [],
            uniqueGeneratedTimetableOccasionalMarkers: methods.uniqueGeneratedTimetableOccasionalMarkers,
        }

        expect(methods.generatedTimetableCellHasAdditionalCourse.call(ctx, 1, 5)).toBe(true)
        expect(methods.generatedTimetableCellHasAdditionalCourse.call(ctx, 2, 6)).toBe(true)
        expect(methods.generatedTimetableCellHasAdditionalCourse.call(ctx, 3, 7)).toBe(false)
    })

    it('uses configured school hours for generated timetable result times when slot times are missing', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            schoolHours: [
                {
                    hour: 5,
                    from: '11:10',
                    until: '11:55',
                },
            ],
            generatedTimetable: {
                slots: {
                    '1-5': {
                        key: 'slot-1',
                        code: 'M6',
                        courseGroup: {
                            hour: 5,
                        },
                    },
                },
            },
            generatedTimetableSchoolHour: methods.generatedTimetableSchoolHour,
            generatedTimetableConfiguredSchoolHour: methods.generatedTimetableConfiguredSchoolHour,
            generatedTimetableHourTimeFrom: methods.generatedTimetableHourTimeFrom,
            generatedTimetableHourTimeUntil: methods.generatedTimetableHourTimeUntil,
            formatTimeValue: methods.formatTimeValue,
        }

        expect(computed.generatedTimetableHours.call(ctx)).toEqual([
            {
                value: 5,
                hourLabel: '5.',
                timeFrom: '11:10',
                timeUntil: '11:55',
            },
        ])
    })
})
