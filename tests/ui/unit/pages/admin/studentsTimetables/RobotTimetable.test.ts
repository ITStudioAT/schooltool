import { describe, expect, it, vi } from 'vitest'
import RobotTimetable from '@/pages/admin/studentsTimetables/robot/RobotTimetable.vue'

function robotContext(overrides = {}) {
    return {
        selection: {},
        constraints: {},
        studentSelection: null,
        selectedCourses: [{ key: 'M-1' }],
        deselectedCourseKeys: [],
        deselectedCourseGroupKeys: [],
        selectedAdditionalCourses: [],
        additionalCourseTimetableRequired: false,
        selectedTimetableResultType: 'full_green',
        evaluationCriteria: [],
        timetableResultCounter: () => 1,
        storageEvaluationCriteria: RobotTimetable.methods.storageEvaluationCriteria,
        normalizedEvaluationCriteria: RobotTimetable.methods.normalizedEvaluationCriteria,
        cloneCriteria: RobotTimetable.methods.cloneCriteria,
        formatNumber: RobotTimetable.methods.formatNumber,
        selectedQualityMetricValue: RobotTimetable.methods.selectedQualityMetricValue,
        selectedQualityMetricLabel: RobotTimetable.methods.selectedQualityMetricLabel,
        selectedQualityMetricReachedBest: RobotTimetable.methods.selectedQualityMetricReachedBest,
        qualityCounterFulfilledCountLabel: RobotTimetable.methods.qualityCounterFulfilledCountLabel,
        qualityCounterBestValueLabel: RobotTimetable.methods.qualityCounterBestValueLabel,
        qualitySummaryCheckedKeys: [],
        selectedQualityCriteriaCount: null,
        qualityCriteriaResultFilterEnabled: false,
        qualityCriteriaResultFilterActive: false,
        qualityCriterionRows: [],
        qualitySummaryCheckboxKey: RobotTimetable.methods.qualitySummaryCheckboxKey,
        selectedQualityCriterionKeys: RobotTimetable.methods.selectedQualityCriterionKeys,
        selectedQualitySummaryCriterion: RobotTimetable.methods.selectedQualitySummaryCriterion,
        selectedCriteriaTimetableCount: RobotTimetable.methods.selectedCriteriaTimetableCount,
        qualityCriteriaResultFilterAvailable: RobotTimetable.methods.qualityCriteriaResultFilterAvailable,
        setQualityCriteriaResultFilterEnabled: RobotTimetable.methods.setQualityCriteriaResultFilterEnabled,
        withExclusiveDistanceLearningPreference: RobotTimetable.methods.withExclusiveDistanceLearningPreference,
        oppositeDistanceLearningPreferenceKey: RobotTimetable.methods.oppositeDistanceLearningPreferenceKey,
        timetableCalculationReady: () => false,
        timetableResultCounterLimit: () => 2160,
        setTimetableResultCounter: () => {},
        normalizeTimetableResultCounters: () => {},
        loadFullGreenTimetableCount: () => {},
        loadQualityCountersForSelectedTimetableType: () => {},
        saveLastRobotState: () => {},
        emitAutomaticStepChange: () => {},
        $emit: () => {},
        ...overrides,
    }
}

describe('RobotTimetable', () => {
    it('does not request quality counters on the blocking timetable request by default', () => {
        const context = robotContext({
            evaluationCriteria: [
                { key: 'saturday_free', enabled: true, priority: 1 },
                { key: 'free_days', enabled: false, priority: 2 },
            ],
        })

        const payload = RobotTimetable.methods.backendTimetableRequestPayload.call(context)

        expect(payload.include_quality_counters).toBe(false)
        expect(payload.selected_quality_criteria_required).toBe(false)
        expect(payload.selected_quality_criterion_keys).toEqual([])
        expect(payload.evaluation_criteria).toEqual([
            { key: 'saturday_free', enabled: true, priority: 1, option: null },
            { key: 'free_days', enabled: false, priority: 2, option: null },
        ])
    })

    it('can explicitly request quality counters', () => {
        const payload = RobotTimetable.methods.backendTimetableRequestPayload.call(
            robotContext(),
            { calculateQualityCounters: true },
        )

        expect(payload.include_quality_counters).toBe(true)
    })

    it('sends checked quality criteria with counter requests', () => {
        const payload = RobotTimetable.methods.backendTimetableRequestPayload.call(robotContext({
            qualitySummaryCheckedKeys: ['saturday_free|', 'free_days|'],
            qualityCriterionRows: [
                { key: 'saturday_free', option: null },
                { key: 'free_days', option: null },
            ],
        }))

        expect(payload.selected_quality_criterion_keys).toEqual(['saturday_free', 'free_days'])
    })

    it('requires checked criteria only when the criteria timetable filter is active', () => {
        const payload = RobotTimetable.methods.backendTimetableRequestPayload.call(robotContext({
            qualityCriteriaResultFilterActive: true,
            qualitySummaryCheckedKeys: ['saturday_free|', 'free_days|'],
            qualityCriterionRows: [
                { key: 'saturday_free', option: null },
                { key: 'free_days', option: null },
            ],
        }))

        expect(payload.selected_quality_criteria_required).toBe(true)
        expect(payload.selected_quality_criterion_keys).toEqual(['saturday_free', 'free_days'])
    })

    it('shows fulfilled criteria as count over total', () => {
        const label = RobotTimetable.methods.qualityCounterFulfilledCountLabel.call(robotContext(), {
            enabled: true,
            count: 12,
            total: 40,
        })

        expect(label).toBe('12 / 40')
    })

    it('explains criteria counts with their best value', () => {
        const label = RobotTimetable.methods.qualityCounterSummaryLabel.call(robotContext(), {
            enabled: true,
            key: 'free_days',
            label: 'Anzahl freie Tage',
            count: 4,
            total: 1296,
            best_label: '3 freie Tage',
        })

        expect(label.replace(/\u00a0/gu, ' ')).toBe('Anzahl freie Tage: 4 / 1 296 (3 freie Tage)')
    })

    it('marks selected quality counters from the rendered timetable metrics', () => {
        const counter = RobotTimetable.methods.qualityCounterWithSelectedTimetableMetrics.call(robotContext(), {
            key: 'free_days',
            enabled: true,
            best_value: 4,
        }, {
            free_days: 4,
        })

        expect(counter.selected_label).toBe('4 freie Tage')
        expect(counter.selected_reached).toBe(true)

        const distanceLearningCounter = RobotTimetable.methods.qualityCounterWithSelectedTimetableMetrics.call(robotContext(), {
            key: 'prefer_distance_learning',
            enabled: true,
            best_value: 1,
        }, {
            distance_learning_count: 1,
        })

        expect(distanceLearningCounter.selected_label).toBe('1 FU-Kurse')
        expect(distanceLearningCounter.selected_reached).toBe(true)

        const avoidDistanceLearningCounter = RobotTimetable.methods.qualityCounterWithSelectedTimetableMetrics.call(robotContext(), {
            key: 'avoid_distance_learning',
            enabled: true,
            best_value: 0,
        }, {
            distance_learning_count: 0,
        })

        expect(avoidDistanceLearningCounter.selected_label).toBe('0 FU-Kurse')
        expect(avoidDistanceLearningCounter.selected_reached).toBe(true)
    })

    it('toggles summary checkboxes without changing criteria data', () => {
        const context = robotContext()
        const counter = { key: 'free_days', option: null }

        RobotTimetable.methods.setQualitySummaryCheckboxChecked.call(context, counter, true)

        expect(context.qualitySummaryCheckedKeys).toEqual(['free_days|'])
        expect(context.selectedQualityCriteriaCount).toBeNull()
        expect(RobotTimetable.methods.qualitySummaryCheckboxChecked.call(context, counter)).toBe(true)

        RobotTimetable.methods.setQualitySummaryCheckboxChecked.call(context, counter, false)

        expect(context.qualitySummaryCheckedKeys).toEqual([])
        expect(RobotTimetable.methods.qualitySummaryCheckboxChecked.call(context, counter)).toBe(false)
    })

    it('refreshes quality counters when a summary checkbox changes after calculation', () => {
        const loadQualityCountersForSelectedTimetableType = vi.fn()
        const context = robotContext({
            selectedQualityCriteriaCount: 12,
            timetableCalculationReady: () => true,
            loadQualityCountersForSelectedTimetableType,
        })

        RobotTimetable.methods.setQualitySummaryCheckboxChecked.call(context, { key: 'saturday_free', option: null }, true)

        expect(context.selectedQualityCriteriaCount).toBeNull()
        expect(loadQualityCountersForSelectedTimetableType).toHaveBeenCalledOnce()
    })

    it('reloads the first criteria timetable when a summary checkbox changes while criteria filtering is active', () => {
        const loadFullGreenTimetableCount = vi.fn()
        const loadQualityCountersForSelectedTimetableType = vi.fn()
        const setTimetableResultCounter = vi.fn()
        const context = robotContext({
            qualityCriteriaResultFilterActive: true,
            qualityCriteriaResultFilterEnabled: true,
            selectedQualityCriteriaCount: 12,
            timetableCalculationReady: () => true,
            loadFullGreenTimetableCount,
            loadQualityCountersForSelectedTimetableType,
            setTimetableResultCounter,
        })

        RobotTimetable.methods.setQualitySummaryCheckboxChecked.call(context, { key: 'free_days', option: null }, true)

        expect(context.selectedQualityCriteriaCount).toBeNull()
        expect(setTimetableResultCounter).toHaveBeenCalledWith('full_green', 1)
        expect(loadFullGreenTimetableCount).toHaveBeenCalledWith({
            calculateQualityCounters: true,
            preserveGeneratedTimetable: true,
        })
        expect(loadQualityCountersForSelectedTimetableType).not.toHaveBeenCalled()
    })

    it('restores a generated timetable for the embedded result route', async () => {
        const loadFullGreenTimetableCount = vi.fn()
        const context = robotContext({
            embeddedCourseCardsOnly: true,
            restoreGeneratedTimetable: true,
            selectedRobotTimetable: null,
            timetableCalculationReady: () => true,
            loadFullGreenTimetableCount,
        })

        await RobotTimetable.methods.restoreGeneratedTimetableFromSavedState.call(context)

        expect(loadFullGreenTimetableCount).toHaveBeenCalledWith({
            skipAdditionalCourseDefaultSelection: true,
            skipQualityCriteriaDefaultSelection: true,
        })
    })

    it('does not restore a generated timetable outside the embedded result route', async () => {
        const loadFullGreenTimetableCount = vi.fn()
        const context = robotContext({
            embeddedCourseCardsOnly: true,
            restoreGeneratedTimetable: false,
            selectedRobotTimetable: null,
            timetableCalculationReady: () => true,
            loadFullGreenTimetableCount,
        })

        await RobotTimetable.methods.restoreGeneratedTimetableFromSavedState.call(context)

        expect(loadFullGreenTimetableCount).not.toHaveBeenCalled()
    })

    it('keeps embedded course cards loading while selected student history loads', () => {
        const context = robotContext({
            embeddedCourseCardsOnly: true,
            loading: false,
            studentCompletedCoursesLoading: true,
            availableCourses: [{ key: 'D1' }],
        })

        expect(RobotTimetable.computed.courseCardsLoading.call(context)).toBe(true)
        expect(RobotTimetable.computed.courseCardsReady.call(context)).toBe(false)

        context.studentCompletedCoursesLoading = false

        expect(RobotTimetable.computed.courseCardsLoading.call(context)).toBe(false)
        expect(RobotTimetable.computed.courseCardsReady.call(context)).toBe(true)
    })

    it('shows the latest checked criteria count on the criteria timetable card', () => {
        const context = robotContext({
            qualitySummaryCheckedKeys: ['saturday_free|', 'free_days|'],
            qualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 1296 },
                { key: 'free_days', option: null, count: 4 },
            ],
        })

        const label = RobotTimetable.computed.selectedCriteriaTimetableCountLabel.call(context)

        expect(label).toBe('4')
    })

    it('shows the selected criteria subset count on the criteria timetable card', () => {
        const context = robotContext({
            selectedQualityCriteriaCount: 1296,
            qualitySummaryCheckedKeys: ['saturday_free|'],
            qualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 1296 },
            ],
        })

        const label = RobotTimetable.computed.selectedCriteriaTimetableCountLabel.call(context)

        expect(label.replace(/\u00a0/gu, ' ')).toBe('1 296')
    })

    it('uses the checked quality criterion count before the backend subset count', () => {
        const context = robotContext({
            selectedQualityCriteriaCount: 8,
            qualitySummaryCheckedKeys: ['saturday_free|'],
            qualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 3 },
            ],
        })

        expect(RobotTimetable.methods.selectedCriteriaTimetableCount.call(context)).toBe(3)
        expect(RobotTimetable.computed.selectedTimetableResultCount.call({
            ...context,
            qualityCriteriaResultFilterActive: true,
        })).toBe(3)
    })

    it('pages by the checked criterion count when the first generated timetable selects it', () => {
        const context = robotContext({
            qualityCriteriaResultFilterEnabled: false,
            selectedQualityCriteriaCount: 8,
            qualitySummaryCheckedKeys: ['saturday_free|'],
            qualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 5 },
            ],
        })

        context.qualityCriteriaResultFilterActive = RobotTimetable.computed.qualityCriteriaResultFilterActive.call(context)

        expect(context.qualityCriteriaResultFilterActive).toBe(true)
        expect(RobotTimetable.computed.selectedTimetableResultCount.call(context)).toBe(5)
    })

    it('activates the criteria timetable view when a counted summary checkbox is checked', () => {
        const context = robotContext({
            qualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 1296 },
            ],
        })

        RobotTimetable.methods.setQualitySummaryCheckboxChecked.call(
            context,
            { key: 'saturday_free', option: null, count: 1296 },
            true,
        )

        expect(context.qualityCriteriaResultFilterEnabled).toBe(true)
        expect(RobotTimetable.computed.qualityCriteriaResultFilterActive.call(context)).toBe(true)
        expect(RobotTimetable.methods.selectedCriteriaTimetableCount.call(context)).toBe(1296)
    })

    it('selects the first counted quality criterion by default', () => {
        const setTimetableResultCounter = vi.fn()
        const normalizeTimetableResultCounters = vi.fn()
        const context = robotContext({
            activeQualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 4 },
                { key: 'free_days', option: null, count: 8 },
            ],
            setTimetableResultCounter,
            normalizeTimetableResultCounters,
        })

        const selected = RobotTimetable.methods.autoSelectFirstQualityCriterionTimetable.call(context)

        expect(selected).toBe(true)
        expect(context.qualitySummaryCheckedKeys).toEqual(['saturday_free|'])
        expect(context.selectedQualityCriteriaCount).toBeNull()
        expect(context.qualityCriteriaResultFilterEnabled).toBe(true)
        expect(setTimetableResultCounter).toHaveBeenCalledWith('full_green', 1)
        expect(normalizeTimetableResultCounters).toHaveBeenCalledOnce()
    })

    it('does not replace an existing quality criterion selection by default', () => {
        const context = robotContext({
            qualitySummaryCheckedKeys: ['free_days|'],
            activeQualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 4 },
            ],
        })

        const selected = RobotTimetable.methods.autoSelectFirstQualityCriterionTimetable.call(context)

        expect(selected).toBe(false)
        expect(context.qualitySummaryCheckedKeys).toEqual(['free_days|'])
        expect(context.qualityCriteriaResultFilterEnabled).toBe(false)
    })

    it('uses the selected criteria count when the criteria card checkbox is active', () => {
        const context = robotContext({
            qualityCriteriaResultFilterActive: true,
            qualityCriteriaResultFilterEnabled: true,
            selectedQualityCriteriaCount: 1296,
            qualitySummaryCheckedKeys: ['saturday_free|'],
            qualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 1296 },
            ],
        })

        expect(RobotTimetable.computed.qualityCriteriaResultFilterActive.call(context)).toBe(true)
        expect(RobotTimetable.computed.selectedTimetableResultCount.call(context)).toBe(1296)
    })

    it('toggles the criteria timetable view from the criteria card', () => {
        const loadFullGreenTimetableCount = vi.fn()
        const setTimetableResultCounter = vi.fn()
        const context = robotContext({
            selectedQualityCriteriaCount: 1296,
            qualitySummaryCheckedKeys: ['saturday_free|'],
            qualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 1296 },
            ],
            timetableCalculationReady: () => true,
            loadFullGreenTimetableCount,
            setTimetableResultCounter,
        })

        RobotTimetable.methods.setQualityCriteriaResultFilterEnabled.call(context, true)

        expect(context.qualityCriteriaResultFilterEnabled).toBe(true)
        expect(setTimetableResultCounter).toHaveBeenCalledWith('full_green', 1)
        expect(loadFullGreenTimetableCount).toHaveBeenCalledWith({
            preserveGeneratedTimetable: true,
            preserveQualityCounters: true,
        })
    })

    it('toggles the criteria timetable view when the card is clicked', () => {
        const context = robotContext({
            selectedQualityCriteriaCount: 1296,
            qualitySummaryCheckedKeys: ['saturday_free|'],
            qualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 1296 },
            ],
        })

        RobotTimetable.methods.toggleQualityCriteriaResultFilter.call(context)

        expect(context.qualityCriteriaResultFilterEnabled).toBe(true)
    })

    it('does not toggle the criteria timetable view when no criteria are selected', () => {
        const setTimetableResultCounter = vi.fn()
        const context = robotContext({
            setTimetableResultCounter,
        })

        RobotTimetable.methods.toggleQualityCriteriaResultFilter.call(context)

        expect(context.qualityCriteriaResultFilterEnabled).toBe(false)
        expect(setTimetableResultCounter).not.toHaveBeenCalled()
    })

    it('returns from the generated timetable to course selection', () => {
        const clearGeneratedTimetables = vi.fn()
        const showCourseActionAfterCourseInteraction = vi.fn()
        const saveLastRobotState = vi.fn()
        const emitAutomaticStepChange = vi.fn()

        RobotTimetable.methods.returnToCourseSelectionFromGeneratedTimetable.call({
            clearGeneratedTimetables,
            showCourseActionAfterCourseInteraction,
            saveLastRobotState,
            emitAutomaticStepChange,
        })

        expect(clearGeneratedTimetables).toHaveBeenCalledOnce()
        expect(showCourseActionAfterCourseInteraction).toHaveBeenCalledOnce()
        expect(emitAutomaticStepChange).toHaveBeenCalledWith('')
        expect(saveLastRobotState).toHaveBeenCalledOnce()
    })

    it('clears additional course selections when returning to regular course selection', () => {
        const clearGeneratedTimetables = vi.fn()
        const emitAutomaticStepChange = vi.fn()
        const saveLastRobotState = vi.fn()
        const emit = vi.fn()
        const context = robotContext({
            additionalCourseSelectedKeys: ['D2'],
            additionalCourseTimetableRequired: true,
            additionalCourseExtensionActionHidden: true,
            additionalCourseSelectionChangedAfterTimetable: true,
            deselectedCourseGroupKeys: ['regular-group', 'additional-group'],
            additionalCourseGroupSelectionKeys: () => ['additional-group'],
            clearAdditionalCourseSelectionState: RobotTimetable.methods.clearAdditionalCourseSelectionState,
            clearGeneratedTimetables,
            emitAutomaticStepChange,
            saveLastRobotState,
            $emit: emit,
        })

        RobotTimetable.methods.returnToCourseSelectionFromGeneratedTimetable.call(context, {
            regularCourseSelection: true,
        })

        expect(context.additionalCourseSelectedKeys).toEqual([])
        expect(context.additionalCourseTimetableRequired).toBe(false)
        expect(context.additionalCourseExtensionActionHidden).toBe(false)
        expect(context.additionalCourseSelectionChangedAfterTimetable).toBe(false)
        expect(context.deselectedCourseGroupKeys).toEqual(['regular-group'])
        expect(clearGeneratedTimetables).toHaveBeenCalledWith({ keepAdditionalCoursePanelVisible: false })
        expect(emit).toHaveBeenCalledWith('generated-timetable-visibility-change', false)
        expect(emitAutomaticStepChange).toHaveBeenCalledWith()
        expect(saveLastRobotState).toHaveBeenCalledOnce()
    })

    it('does not change course selection while the embedded generated timetable is locked', () => {
        const context = {
            embeddedCourseSelectionLocked: true,
            deselectedCourseKeys: [],
            deselectedCourseGroupKeys: [],
            additionalCourseSelectedKeys: [],
            setCourseSelectedState: vi.fn(),
            setCourseGroupsSelectedState: vi.fn(),
            clearGeneratedTimetables: vi.fn(),
            saveLastRobotState: vi.fn(),
        }

        RobotTimetable.methods.setCourseSelected.call(context, { key: 'M-1' }, false)

        expect(context.setCourseSelectedState).not.toHaveBeenCalled()
        expect(context.setCourseGroupsSelectedState).not.toHaveBeenCalled()
        expect(context.clearGeneratedTimetables).not.toHaveBeenCalled()
        expect(context.saveLastRobotState).not.toHaveBeenCalled()
    })
})
