import { describe, expect, it, vi } from 'vitest'
import TimetableV2 from '@/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue'

function timetableV2Context(overrides = {}) {
    const context = {
        ...TimetableV2.methods,
        $route: {
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {},
        },
        $router: {
            push: vi.fn(() => Promise.resolve()),
            replace: vi.fn(() => Promise.resolve()),
        },
        timetableV2Step: 'selection',
        timetableStartMode: '',
        schoolHours: [],
        storedTimetableStudentContext: null,
        storedTimetableState: null,
        noStudentSelectedSemester: 1,
        selectedCourseItems: [],
        selectedReviewCourseItem: null,
        timetableCalculationLoading: false,
        timetableCalculationResult: null,
        timetableCalculationSelectedNumber: 1,
        selectedMoreCourseKey: '',
        formatNumber: TimetableV2.methods.formatNumber,
        formatTimeValue: TimetableV2.methods.formatTimeValue,
        spacedCourseCode: TimetableV2.methods.spacedCourseCode,
        uniqueValues: TimetableV2.methods.uniqueValues,
        courseReviewVisible: false,
        timetableCalculationVisible: false,
        loadCourseGroups: vi.fn(),
        loadSchoolHours: vi.fn(),
        loadSubjectRows: vi.fn(),
        saveStoredTimetableState: vi.fn(),
        defaultStoredTimetableState: () => ({
            selection: {},
            timetableV2Selection: {},
            transferredStudentContext: null,
        }),
        defaultNoStudentTimetableV2Selection: () => ({ semester: 1 }),
        storedTimetableStateForSaving: () => null,
        calculateTimetables: vi.fn(),
        ...overrides,
    }

    Object.defineProperties(context, {
        selectedTimetableV2Result: {
            get() {
                return TimetableV2.computed.selectedTimetableV2Result.call(context)
            },
        },
        timetableCalculationResultCountItems: {
            get() {
                return TimetableV2.computed.timetableCalculationResultCountItems.call(context)
            },
        },
        timetableCalculationValidResultCount: {
            get() {
                return TimetableV2.computed.timetableCalculationValidResultCount.call(context)
            },
        },
        timetableCalculationConflictResultCount: {
            get() {
                return TimetableV2.computed.timetableCalculationConflictResultCount.call(context)
            },
        },
        timetableCalculationUnavailableLabel: {
            get() {
                return TimetableV2.computed.timetableCalculationUnavailableLabel.call(context)
            },
        },
        selectedCourseItemsClickable: {
            get() {
                return TimetableV2.computed.selectedCourseItemsClickable.call(context)
            },
        },
        storedTimetableV2Selection: {
            get() {
                return TimetableV2.computed.storedTimetableV2Selection.call(context)
            },
        },
        offeredCourseSelectionOverrides: {
            get() {
                return TimetableV2.computed.offeredCourseSelectionOverrides.call(context)
            },
        },
        selectedTimetableV2ResultCount: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ResultCount.call(context)
            },
        },
        selectedTimetableV2ResultType: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ResultType.call(context)
            },
        },
        selectedTimetableV2ResultTypeCounterLabel: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ResultTypeCounterLabel.call(context)
            },
        },
        selectedTimetableV2TitleLabel: {
            get() {
                return TimetableV2.computed.selectedTimetableV2TitleLabel.call(context)
            },
        },
        selectedTimetableV2ConflictSeverity: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ConflictSeverity.call(context)
            },
        },
        selectedTimetableV2ConflictIcon: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ConflictIcon.call(context)
            },
        },
        selectedTimetableV2ConflictTitle: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ConflictTitle.call(context)
            },
        },
        selectedTimetableV2ConflictSummaryItems: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ConflictSummaryItems.call(context)
            },
        },
        selectedTimetableV2ConflictResolutionOptions: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ConflictResolutionOptions.call(context)
            },
        },
        moreCoursesCardItems: {
            get() {
                return TimetableV2.computed.moreCoursesCardItems.call(context)
            },
        },
        selectedMoreCourseItem: {
            get() {
                return TimetableV2.computed.selectedMoreCourseItem.call(context)
            },
        },
        selectedMoreCourseOfferedCourseItems: {
            get() {
                return TimetableV2.computed.selectedMoreCourseOfferedCourseItems.call(context)
            },
        },
        selectedTimetableV2CounterLabel: {
            get() {
                return TimetableV2.computed.selectedTimetableV2CounterLabel.call(context)
            },
        },
        selectedTimetableV2ResultCountLabel: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ResultCountLabel.call(context)
            },
        },
        selectedTimetableV2PreviousAvailable: {
            get() {
                return TimetableV2.computed.selectedTimetableV2PreviousAvailable.call(context)
            },
        },
        selectedTimetableV2NextAvailable: {
            get() {
                return TimetableV2.computed.selectedTimetableV2NextAvailable.call(context)
            },
        },
        selectedTimetableV2SlotEntries: {
            get() {
                return TimetableV2.computed.selectedTimetableV2SlotEntries.call(context)
            },
        },
        selectedTimetableV2Weekdays: {
            get() {
                return TimetableV2.computed.selectedTimetableV2Weekdays.call(context)
            },
        },
        selectedTimetableV2Times: {
            get() {
                return TimetableV2.computed.selectedTimetableV2Times.call(context)
            },
        },
    })

    return context
}

describe('TimetableV2 route steps', () => {
    it('lists unselected planned and additional courses in the more courses card', () => {
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'planned:GW1': false,
                'additional:INF2': true,
            },
            moreCoursesVisible: false,
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, label: 'INF2' },
                { code: 'GS1', hours: 4, label: 'GS1' },
            ],
            courseGroups: [
                { class_name: 'GW1-1C-HÖF', course: 'GW1', hour: 12, title: 'GW1', weekday: 3 },
                { class_name: 'GS1-2A-PLÖC', course: 'GS1', hour: 13, title: 'GS1', weekday: 4 },
            ],
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, label: 'D1' },
                { code: 'GW1', hours: 4, label: 'GW1' },
            ],
        })

        expect(context.moreCoursesCardItems.map((course) => ({
            courseGroupLabel: course.courseGroupLabel,
            label: course.label,
            meta: course.meta,
        }))).toEqual([
            { courseGroupLabel: 'Vorgesehene Kurse', label: 'GW1', meta: '4 Std.' },
            { courseGroupLabel: 'Zusätzliche Kurse', label: 'GS1', meta: '4 Std.' },
        ])

        TimetableV2.methods.toggleMoreCoursesCard.call(context)

        expect(context.moreCoursesVisible).toBe(true)

        TimetableV2.methods.selectMoreCourse.call(context, context.moreCoursesCardItems[0])

        expect(context.selectedMoreCourseItem?.label).toBe('GW1')
        expect(context.selectedMoreCourseOfferedCourseItems.map((course) => course.groupSelectionLabel)).toEqual([
            'GW1-1C-HÖF',
        ])
    })

    it('pushes the current step and mode into the route query', () => {
        const context = timetableV2Context({
            timetableStartMode: 'without-student',
        })

        TimetableV2.methods.setTimetableV2Step.call(context, 'course-review')

        expect(context.timetableV2Step).toBe('course-review')
        expect(context.$router.push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                step: 'course-review',
                mode: 'without-student',
            },
        })
    })

    it('restores a no-student step from the route query', () => {
        const context = timetableV2Context({
            $route: {
                path: '/admin/students-timetables/timetable-v2/overview',
                query: {
                    step: 'course-review',
                    mode: 'without-student',
                },
            },
        })

        TimetableV2.methods.applyTimetableV2RouteFromRoute.call(context, {
            restoreEffects: false,
            syncRoute: false,
        })

        expect(context.timetableStartMode).toBe('without-student')
        expect(context.timetableV2Step).toBe('course-review')
    })

    it('replaces invalid route step values with the selection step', () => {
        const context = timetableV2Context({
            $route: {
                path: '/admin/students-timetables/timetable-v2/overview',
                query: {
                    step: 'unknown',
                    mode: 'also-unknown',
                },
            },
        })

        TimetableV2.methods.applyTimetableV2RouteFromRoute.call(context, {
            restoreEffects: false,
        })

        expect(context.timetableV2Step).toBe('selection')
        expect(context.$router.replace).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                step: 'selection',
            },
        })
    })

    it('keeps the selected calculation timetable number in the route and payload', () => {
        const context = timetableV2Context({
            $route: {
                path: '/admin/students-timetables/timetable-v2/overview',
                query: {
                    step: 'timetable-calculation',
                    mode: 'student',
                    tt: '3',
                },
            },
            storedTimetableStudentContext: {
                student: { studentCode: '1001' },
                courses: {},
            },
            timetableCalculationResult: {
                timetable_variation_count: 450,
                full_green_timetable_count: 100,
                green_timetable_count: 350,
                conflict_timetable_count: 0,
                selected_timetable: {
                    number: 3,
                    type: 'full_green',
                    slots: {},
                },
            },
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.applyTimetableV2RouteFromRoute.call(context, {
            restoreEffects: false,
            syncRoute: false,
        })

        expect(context.timetableCalculationSelectedNumber).toBe(3)
        expect(TimetableV2.methods.timetableV2RouteLocation.call(context)).toEqual({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                step: 'timetable-calculation',
                mode: 'student',
                tt: '3',
            },
        })
        expect(TimetableV2.methods.timetableV2CalculationPayload.call(context).selected_timetable_number).toBe(3)
        expect(TimetableV2.computed.timetableCalculationCountLabel.call(context)).toBe('450 Stundenpläne gesamt')
        expect(context.selectedTimetableV2CounterLabel).toBe('3 / 450 gültige')
        expect(context.selectedTimetableV2ResultCountLabel).toBe('450 gültige Stundenpläne')
    })

    it('falls back to green timetable results when no full-green timetable is available', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                full_green_timetable_count: 0,
                green_timetable_count: 4,
                conflict_timetable_count: 12,
                selected_timetable: null,
            },
        })

        expect(TimetableV2.methods.timetableV2FallbackSelectedTimetableType.call(context, context.timetableCalculationResult)).toBe('green')
        expect(TimetableV2.methods.timetableV2CalculationPayload.call(context, {
            selectedTimetableType: 'green',
        }).selected_timetable_type).toBe('green')
    })

    it('falls back to valid timetable results when a conflict result still has valid timetables', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                full_green_timetable_count: 12,
                green_timetable_count: 0,
                conflict_timetable_count: 4,
                selected_timetable: {
                    number: 1,
                    type: 'conflict',
                    slots: {},
                },
            },
        })

        const fallbackRequest = TimetableV2.methods.timetableV2FallbackSelectedTimetableRequest.call(
            context,
            context.timetableCalculationResult,
            1,
        )

        expect(fallbackRequest).toEqual({
            selectedTimetableType: 'full_green',
            selectedTimetableNumber: 1,
        })
    })

    it('maps combined valid timetable numbers after full-green results to green requests', () => {
        const context = timetableV2Context({
            timetableCalculationSelectedNumber: 8,
            timetableCalculationResult: {
                full_green_timetable_count: 6,
                green_timetable_count: 4,
                conflict_timetable_count: 0,
                selected_timetable: {
                    number: 6,
                    type: 'full_green',
                    slots: {},
                },
            },
        })
        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.selectedTimetableV2CounterLabel).toBe('8 / 10 gültige')
        expect(payload.selected_timetable_type).toBe('green')
        expect(payload.selected_timetable_number).toBe(2)
    })

    it('shows conflict timetable results when no valid timetable is available', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                timetable_variation_count: 16,
                full_green_timetable_count: 0,
                green_timetable_count: 0,
                conflict_timetable_count: 16,
                selected_timetable: null,
            },
        })

        expect(context.timetableCalculationResultCountItems.map((item) => item.label)).toEqual([
            '0 gültig',
            '16 Konflikte',
        ])
        expect(context.timetableCalculationUnavailableLabel).toBe('Es wurden nur Stundenpläne mit Überschneidung gefunden.')
        expect(TimetableV2.methods.timetableV2FallbackSelectedTimetableType.call(context, context.timetableCalculationResult)).toBe('conflict')

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.selected_timetable_type).toBe('conflict')
        expect(payload.selected_timetable_number).toBe(1)
    })

    it('moves to the next timetable result and recalculates it', () => {
        const calculateTimetables = vi.fn()
        const context = timetableV2Context({
            calculateTimetables,
            timetableCalculationResult: {
                full_green_timetable_count: 4,
                selected_timetable: {
                    number: 1,
                    type: 'full_green',
                    slots: {},
                },
            },
            timetableCalculationSelectedNumber: 1,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.moveSelectedTimetableV2Result.call(context, 1)

        expect(context.timetableCalculationSelectedNumber).toBe(2)
        expect(context.$router.push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                step: 'timetable-calculation',
                tt: '2',
            },
        })
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('does not switch selected courses from the calculation summary', () => {
        const context = timetableV2Context({
            timetableV2Step: 'timetable-calculation',
            selectedReviewCourseKey: 'course:one',
        })

        TimetableV2.methods.selectReviewCourse.call(context, {
            selectionKey: 'course:two',
        })

        expect(context.selectedCourseItemsClickable).toBe(false)
        expect(context.selectedReviewCourseKey).toBe('course:one')
    })

    it('does not calculate timetables with fully crossed-out selected courses', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'INF1', selectionKey: 'planned:INF1' },
                { courseGroup: 'planned', key: 'D1', selectionKey: 'planned:D1' },
                { courseGroup: 'additional', key: 'ZUSATZ1', selectionKey: 'additional:ZUSATZ1' },
            ],
            offeredCourseItemsForSelectedCourse: (course) => {
                if (course.key === 'INF1') {
                    return [
                        { selectionKey: 'planned:INF1::INF1-A', backendSelectionKey: 'INF1|A' },
                        { selectionKey: 'planned:INF1::INF1-B', backendSelectionKey: 'INF1|B' },
                    ]
                }

                if (course.key === 'D1') {
                    return [
                        { selectionKey: 'planned:D1::D1-A', backendSelectionKey: 'D1|A' },
                    ]
                }

                return [
                    { selectionKey: 'additional:ZUSATZ1::Z-A', backendSelectionKey: 'ZUSATZ1|A' },
                ]
            },
            storedTimetableState: {
                timetableV2Selection: {
                    offeredCourseSelections: {
                        'planned:INF1::INF1-A': false,
                        'planned:INF1::INF1-B': false,
                    },
                },
            },
        })
        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.selected_course_keys).toEqual(['D1'])
        expect(payload.selected_additional_course_keys).toEqual(['ZUSATZ1'])
        expect(payload.deselected_course_group_keys).toEqual(['INF1|A', 'INF1|B'])
    })

    it('selects only offered distance-learning courses through the selected course bulk option', () => {
        const storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                offeredCourseSelections: {
                    'other-course::other': false,
                    'selected-course::regular': false,
                },
            },
            transferredStudentContext: null,
        }
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            selectedCourseItems: [
                {
                    selectionKey: 'selected-course',
                    offeredCourses: [
                        { selectionKey: 'selected-course::fu', distanceLearning: true },
                        { selectionKey: 'selected-course::regular', distanceLearning: false },
                    ],
                },
                {
                    selectionKey: 'second-course',
                    offeredCourses: [
                        { selectionKey: 'second-course::fu', distanceLearning: true },
                        { selectionKey: 'second-course::regular', distanceLearning: false },
                    ],
                },
            ],
            offeredCourseItemsForSelectedCourse: (course) => course.offeredCourses,
            storedTimetableState,
            storedTimetableStateForSaving: () => storedTimetableState,
            saveStoredTimetableState,
        })

        TimetableV2.methods.applyOfferedCourseBulkSelection.call(context, 'distance-learning')

        expect(saveStoredTimetableState).toHaveBeenCalledWith({
            selection: {},
            timetableV2Selection: {
                offeredCourseSelections: {
                    'other-course::other': false,
                    'selected-course::regular': false,
                    'second-course::regular': false,
                },
            },
            transferredStudentContext: null,
        })
    })

    it('selects all offered courses through the selected course bulk option', () => {
        const storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                offeredCourseSelections: {
                    'other-course::other': false,
                    'selected-course::fu': false,
                    'selected-course::regular': false,
                    'second-course::fu': false,
                    'second-course::regular': false,
                },
            },
            transferredStudentContext: null,
        }
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            selectedCourseItems: [
                {
                    selectionKey: 'selected-course',
                    offeredCourses: [
                        { selectionKey: 'selected-course::fu', distanceLearning: true },
                        { selectionKey: 'selected-course::regular', distanceLearning: false },
                    ],
                },
                {
                    selectionKey: 'second-course',
                    offeredCourses: [
                        { selectionKey: 'second-course::fu', distanceLearning: true },
                        { selectionKey: 'second-course::regular', distanceLearning: false },
                    ],
                },
            ],
            offeredCourseItemsForSelectedCourse: (course) => course.offeredCourses,
            storedTimetableState,
            storedTimetableStateForSaving: () => storedTimetableState,
            saveStoredTimetableState,
        })

        TimetableV2.methods.applyOfferedCourseBulkSelection.call(context, 'all')

        expect(saveStoredTimetableState).toHaveBeenCalledWith({
            selection: {},
            timetableV2Selection: {
                offeredCourseSelections: {
                    'other-course::other': false,
                },
            },
            transferredStudentContext: null,
        })
    })

    it('builds display rows from the first valid selected timetable', () => {
        const context = timetableV2Context({
            schoolHours: [
                { hour: 1, from: '08:00:00', until: '08:50:00' },
                { hour: 2, from: '08:55:00', until: '09:45:00' },
            ],
            courseGroups: [
                { class_name: 'D1-Grp1-KRO', course: 'D1', hour: 1, title: 'D1', weekday: 1 },
                { class_name: 'INF2-Grp1-FU', course: 'INF2', hour: 2, title: 'INF2', weekday: 6 },
                { class_name: 'M1-Grp1-MAY', course: 'M1', hour: 2, title: 'M1', weekday: 6 },
            ],
            selectedCourseItems: [
                { code: 'D1', courseGroup: 'planned', key: 'D1', label: 'D1', selectionKey: 'planned:D1' },
                { code: 'INF2', courseGroup: 'planned', key: 'INF2', label: 'INF2', selectionKey: 'planned:INF2' },
                { code: 'M1', courseGroup: 'planned', key: 'M1', label: 'M1', selectionKey: 'planned:M1' },
            ],
            timetableCalculationResult: {
                selected_timetable: {
                    number: 1,
                    type: 'full_green',
                    statusMessage: 'Voller grüner Stundenplan',
                    problems: [
                        'INF2 Informatik INF2-Grp1-FU überschneidet sich mit M1 Mathematik M1-Grp1-MAY (2026-03-10, 2026-03-17).',
                    ],
                    slots: {
                        '1-1': {
                            code: 'D1',
                            sourceLabel: 'D1-Grp1-KRO',
                            dateRangeLabel: '23.02.-29.6.',
                            courseGroup: { weekday: 1, hour: 1, recurrence_interval: 1 },
                            isDistanceLearningCourse: false,
                            conflicts: [
                                {
                                    key: 'm1-d1',
                                    code: 'M1',
                                    name: 'Mathematik',
                                    sourceLabel: 'M1-Grp1-MAY',
                                    dateRangeLabel: '21.02.-18.4.',
                                    courseGroup: { recurrence_interval: 3 },
                                    isOccasional: false,
                                },
                            ],
                            sameSlotEntries: [
                                {
                                    code: 'D2',
                                    sourceLabel: 'D2-Grp1-KRO',
                                    dateRangeLabel: '16.02.-22.2.',
                                    courseGroup: { weekday: 1, hour: 1, recurrence_interval: 1 },
                                    isDistanceLearningCourse: false,
                                },
                            ],
                        },
                        '6-2': {
                            code: 'INF2',
                            sourceLabel: 'INF2-Grp1-FU',
                            dateRangeLabel: '21.02.-18.4.',
                            courseGroup: { weekday: 6, hour: 2, recurrence_interval: 2 },
                            isAdditionalCourse: true,
                            isDistanceLearningCourse: true,
                            conflicts: [
                                {
                                    key: 'm1',
                                    label: 'M1 Mathematik M1-Grp1-MAY 21.02.-18.4.',
                                    code: 'M1',
                                    name: 'Mathematik',
                                    sourceLabel: 'M1-Grp1-MAY',
                                    dateRangeLabel: '21.02.-18.4.',
                                    courseGroup: { recurrence_interval: 3 },
                                    isOccasional: false,
                                },
                            ],
                        },
                    },
                },
            },
        })

        expect(TimetableV2.computed.selectedTimetableV2Result.call(context)?.number).toBe(1)
        expect(TimetableV2.computed.selectedTimetableV2StatusLabel.call(context)).toBe('Voller grüner Stundenplan')
        expect(TimetableV2.computed.selectedTimetableV2Weekdays.call(context).map((weekday) => weekday.value)).toEqual([1, 2, 3, 4, 5, 6])
        expect(TimetableV2.computed.selectedTimetableV2Times.call(context)).toEqual([
            { hourLabel: '1.', timeFrom: '08:00', timeUntil: '08:50', value: 1 },
            { hourLabel: '2.', timeFrom: '08:55', timeUntil: '09:45', value: 2 },
        ])
        expect(TimetableV2.methods.selectedTimetableV2SlotTitle.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'])).toBe('INF 2')
        expect(TimetableV2.methods.selectedTimetableV2SlotDetails.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'])).toBe('INF2-Grp1-FU')
        expect(TimetableV2.methods.selectedTimetableV2SlotRecurrenceLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['1-1'])).toBe('')
        expect(TimetableV2.methods.selectedTimetableV2SlotRecurrenceLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'])).toBe('2-wöchig')
        expect(TimetableV2.methods.selectedTimetableV2SlotDateLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['1-1'])).toBe('')
        expect(TimetableV2.methods.selectedTimetableV2SlotDateLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['1-1'], { showRegularRange: true })).toBe('23.02.-29.6.')
        expect(TimetableV2.methods.selectedTimetableV2SlotDateLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['1-1'].sameSlotEntries[0], { showRegularRange: true })).toBe('16.02.-22.2.')
        expect(TimetableV2.methods.selectedTimetableV2SlotDateLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'])).toBe('')
        expect(TimetableV2.methods.selectedTimetableV2SlotDateLabel.call(context, {
            code: 'WS1',
            sourceLabel: 'WS1-Block',
            dateRangeLabel: '05.03.',
            courseGroup: {
                dates: ['2026-03-05'],
            },
            isOccasional: true,
        })).toBe('05.03.')
        expect(TimetableV2.methods.selectedTimetableV2SlotConflicts.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'])).toHaveLength(1)
        expect(TimetableV2.methods.selectedTimetableV2ConflictLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'].conflicts[0])).toBe('M1-Grp1-MAY 3-wöchig')
        expect(TimetableV2.methods.selectedTimetableV2ConflictLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'].conflicts[0], context.timetableCalculationResult.selected_timetable.slots['6-2'])).toBe('M1-Grp1-MAY 3-wöchig (10.03., 17.03.)')
        expect(context.selectedTimetableV2ConflictSummaryItems).toEqual([
            'D1-Grp1-KRO überschneidet sich mit M1-Grp1-MAY 3-wöchig.',
            'INF2-Grp1-FU überschneidet sich mit M1-Grp1-MAY 3-wöchig (10.03., 17.03.).',
        ])
        expect(context.selectedTimetableV2ConflictResolutionOptions.map((option) => ({
            count: option.count,
            countLabel: option.countLabel,
            label: option.label,
        }))).toEqual([
            { count: 2, countLabel: '2 Konflikte lösen', label: 'M1-Grp1-MAY' },
            { count: 1, countLabel: '1 Konflikt lösen', label: 'D1-Grp1-KRO' },
            { count: 1, countLabel: '1 Konflikt lösen', label: 'INF2-Grp1-FU' },
        ])

        TimetableV2.methods.applySelectedTimetableV2ConflictResolution.call(
            context,
            context.selectedTimetableV2ConflictResolutionOptions[0],
        )

        expect(context.saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                offeredCourseSelections: expect.objectContaining({
                    [context.selectedTimetableV2ConflictResolutionOptions[0].selectionKey]: false,
                }),
            }),
        }))
        expect(context.calculateTimetables).toHaveBeenCalled()
    })

    it('shows occasional-only timetable conflicts as warning overlaps without resolution options', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { code: 'D1', courseGroup: 'planned', key: 'D1', label: 'D1', selectionKey: 'planned:D1' },
                { code: 'LPT', courseGroup: 'planned', key: 'LPT', label: 'LPT', selectionKey: 'planned:LPT' },
                { code: 'M1', courseGroup: 'planned', key: 'M1', label: 'M1', selectionKey: 'planned:M1' },
            ],
            timetableCalculationResult: {
                selected_timetable: {
                    number: 1,
                    type: 'green',
                    statusMessage: 'Grüner Stundenplan mit Einzeltermin-Überschneidung',
                    slots: {
                        '1-1': {
                            code: 'D1',
                            sourceLabel: 'D1-1C-GOS',
                            courseGroup: { weekday: 1, hour: 1, recurrence_interval: 1 },
                            conflicts: [
                                {
                                    key: 'lpt-d1',
                                    code: 'LPT',
                                    sourceLabel: 'LPT-1CK-DREI',
                                    courseGroup: { dates: ['2026-02-17'], weekday: 1, hour: 1 },
                                    isOccasional: true,
                                },
                            ],
                        },
                        '2-1': {
                            code: 'LPT',
                            sourceLabel: 'LPT-1CK-DREI',
                            courseGroup: { dates: ['2026-02-18'], weekday: 2, hour: 1 },
                            isOccasional: true,
                            conflicts: [
                                {
                                    key: 'm1-lpt',
                                    code: 'M1',
                                    sourceLabel: 'M1-1C-MAY',
                                    courseGroup: { weekday: 2, hour: 1, recurrence_interval: 1 },
                                },
                            ],
                        },
                    },
                },
            },
        })

        expect(context.selectedTimetableV2ConflictSeverity).toBe('warning')
        expect(context.selectedTimetableV2ConflictIcon).toBe('mdi-alert-outline')
        expect(context.selectedTimetableV2ConflictTitle).toBe('Überschneidungen')
        expect(context.selectedTimetableV2ConflictSummaryItems).toEqual([
            'D1-1C-GOS überschneidet sich mit LPT-1CK-DREI (17.02.).',
            'LPT-1CK-DREI überschneidet sich mit M1-1C-MAY (18.02.).',
        ])
        expect(context.selectedTimetableV2ConflictResolutionOptions).toEqual([])
        expect(
            TimetableV2.methods.selectedTimetableV2SlotTitle.call(
                context,
                TimetableV2.methods.selectedTimetableV2DisplaySlot.call(
                    context,
                    context.timetableCalculationResult.selected_timetable.slots['2-1'],
                ),
            ),
        ).toBe('M 1')
        expect(TimetableV2.methods.selectedTimetableV2OccasionalOverlapChips.call(
            context,
            context.timetableCalculationResult.selected_timetable.slots['2-1'],
        )).toEqual([
            { key: 'LPT|LPT-1CK-DREI', label: 'LPT-1CK-DREI 18.02.' },
        ])
        expect(TimetableV2.methods.selectedTimetableV2DisplayedSlotConflicts.call(
            context,
            context.timetableCalculationResult.selected_timetable.slots['2-1'],
        )).toEqual([])
        expect(TimetableV2.methods.selectedTimetableV2CellClasses.call(context, 1, 1)).toMatchObject({
            'students-timetable-v2-result-grid__cell--conflict': false,
            'students-timetable-v2-result-grid__cell--filled': true,
        })
    })
})
