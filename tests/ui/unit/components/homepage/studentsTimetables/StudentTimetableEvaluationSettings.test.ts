import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import StudentTimetableEvaluationSettings from '@/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue'

describe('Student timetable evaluation settings', () => {
    it('receives proposed courses from the student overview', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain(':proposed-courses="overview?.proposed_courses || []"')
        expect(source).toContain(':initial-step="automaticTimetableStep"')
        expect(source).toContain(':initial-selected-course-keys="automaticTimetableCourseKeys"')
        expect(source).toContain(':initial-selected-quality-criterion-keys="automaticTimetableQualityCriterionKeys"')
        expect(source).toContain(':default-quality-criterion-selection="!automaticTimetableQualityCriteriaSelectionExplicit"')
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
        expect(source).not.toContain('Zurücksetzen')
        expect(source).not.toContain('@click="resetChanges"')
        expect(source).not.toContain('mdi-close')
        expect(source).not.toContain('Automatischen Stundenplan schließen')
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
        }

        expect(data.call(ctx).selectedCourseKeys).toEqual(['course-2'])
        expect(data.call(ctx).selectedQualityCriterionKeys).toEqual(['saturday_free'])
    })

    it('moves from criteria to a selectable proposed courses step', async () => {
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
            selectAllProposedCourses: methods.selectAllProposedCourses,
            emitCourseSelectionChange: methods.emitCourseSelectionChange,
            get selectedCourses() {
                return proposedCourses.filter(course => methods.courseSelected.call(ctx, course))
            },
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        await methods.continueToNextStep.call(ctx)

        expect(ctx.currentStep).toBe('courses')
        expect(ctx.selectedCourseKeys).toEqual(['course-1', 'course-2'])

        methods.setCourseSelected.call(ctx, proposedCourses[1], false)

        expect(ctx.selectedCourseKeys).toEqual(['course-1'])

        expect(emitted).toEqual([
            {
                event: 'course-selection-change',
                payload: ['course-1', 'course-2'],
            },
            {
                event: 'step-change',
                payload: 'courses',
            },
            {
                event: 'course-selection-change',
                payload: ['course-1'],
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
        expect(emitted).toEqual([])

        await methods.continueToNextStep.call(ctx)

        expect(emitted).toEqual([
            {
                event: 'courses-selected',
                payload: [{ key: 'course-1' }],
            },
        ])
    })

    it('posts selected course keys to the student automatic timetable endpoint', async () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("axios.post('/api/homepage/students-timetables/automatic-timetable'")
        expect(source).toContain('selected_course_keys: this.selectedCourseKeys')
        expect(source).toContain('selected_quality_criterion_keys: this.selectedQualityCriterionKeys')
        expect(source).toContain('selected_timetable_type: this.selectedTimetableType')
        expect(source).toContain('selected_timetable_number: this.selectedTimetableNumber')
        expect(source).toContain("this.moveToStep('result')")
        expect(source).toContain('ensureAutomaticTimetableForResultStep()')
        expect(source).toContain('canMoveGeneratedTimetable(-1)')
        expect(source).toContain('canMoveGeneratedTimetable(1)')
        expect(source).toContain('generatedTimetableDisplaySlot(weekday.value, hour)')
        expect(source).toContain('generatedTimetableOccasionalMarkers(weekday.value, hour)')
        expect(source).toContain('erfüllen das Kriterium')
        expect(source).toContain('Stundenpläne')
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

    it('moves between generated timetable result buckets with arrow controls', async () => {
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

        expect(computed.generatedTimetablePositionLabel.call(ctx)).toBe('2 / 3')

        await methods.moveGeneratedTimetable.call(ctx, 1)

        expect(ctx.selectedTimetableType).toBe('conflict')
        expect(ctx.selectedTimetableNumber).toBe(1)
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
})
