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
        timetableCalculationReady: () => false,
        timetableResultCounterLimit: () => 2160,
        setTimetableResultCounter: () => {},
        normalizeTimetableResultCounters: () => {},
        loadFullGreenTimetableCount: () => {},
        loadQualityCountersForSelectedTimetableType: () => {},
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

    it('shows fulfilled criteria as count over total', () => {
        const label = RobotTimetable.methods.qualityCounterFulfilledCountLabel.call(robotContext(), {
            enabled: true,
            count: 12,
            total: 40,
        })

        expect(label).toBe('12 / 40')
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

    it('does not activate the criteria timetable view until the criteria card checkbox is checked', () => {
        const context = robotContext({
            selectedQualityCriteriaCount: 1296,
            qualitySummaryCheckedKeys: ['saturday_free|'],
            qualityCriterionRows: [
                { key: 'saturday_free', option: null, count: 1296 },
            ],
        })

        expect(RobotTimetable.computed.qualityCriteriaResultFilterActive.call(context)).toBe(false)
        expect(RobotTimetable.computed.selectedTimetableResultCount.call(context)).toBe(2160)
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
        expect(loadFullGreenTimetableCount).toHaveBeenCalledWith({ preserveQualityCounters: true })
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
})
