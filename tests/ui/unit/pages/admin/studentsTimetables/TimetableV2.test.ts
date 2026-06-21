import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import TimetableV2 from '@/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue'

function timetableV2Context(overrides = {}) {
    const courseSelectionOverridesOverride = overrides.courseSelectionOverrides
    const selectedCourseItemsOverride = overrides.selectedCourseItems
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
        subjectRows: [],
        studentCompletedCoursesLoading: false,
        subjectRowsLoading: false,
        courseGroupsLoading: false,
        schoolHoursLoading: false,
        storedTimetableStudentContext: null,
        storedTimetableState: null,
        storedAdditionalCourseItems: [],
        storedMissingCourseCardItems: [],
        storedPlannedCourseItems: [],
        noStudentSelectedSemester: 1,
        selectedCourseItems: [],
        selectedReviewCourseKey: '',
        timetableCalculationError: '',
        timetableCalculationLoading: false,
        timetableCalculationRequestId: 0,
        timetableCalculationResult: null,
        timetableCalculationSelectedNumber: 1,
        initialTimetableCalculationSelectedNumber: 1,
        initialTimetableCalculationSelection: null,
        initialTimetableCalculationNoSaturdaySelected: false,
        timetableNoSaturdayDraftSelected: false,
        timetableNoSaturdaySelected: false,
        timetableOptionsCardVisible: false,
        moreCoursesCardVisible: false,
        selectedMoreCourseKey: '',
        moreCoursesSelectionSnapshot: '',
        courseSelectionSnapshotSelections: {},
        offeredCourseSelectionSnapshotSelections: {},
        moreOfferedCourseSelectionSnapshot: '',
        moreOfferedCourseSelectionSnapshotSelections: {},
        moreCourseAvailabilityLoading: false,
        moreCourseAvailabilityByKey: {},
        moreCourseAvailabilityRequestId: 0,
        moreCourseAvailabilitySignature: '',
        formatNumber: TimetableV2.methods.formatNumber,
        formatTimeValue: TimetableV2.methods.formatTimeValue,
        spacedCourseCode: TimetableV2.methods.spacedCourseCode,
        uniqueValues: TimetableV2.methods.uniqueValues,
        courseReviewVisible: false,
        timetableCalculationVisible: false,
        loadCourseGroups: vi.fn(),
        loadSchoolHours: vi.fn(),
        loadSubjectRows: vi.fn(),
        loadStoredStudentOverview: vi.fn(),
        requestMoreCourseAvailability: vi.fn(() => Promise.resolve({
            data: {
                data: {
                    full_green_timetable_count: 1,
                    green_timetable_count: 0,
                },
            },
        })),
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
        noSaturdayTimetableCount: {
            get() {
                return TimetableV2.computed.noSaturdayTimetableCount.call(context)
            },
        },
        noSaturdayTimetableCountFormatted: {
            get() {
                return TimetableV2.computed.noSaturdayTimetableCountFormatted.call(context)
            },
        },
        timetableOptionsChanged: {
            get() {
                return TimetableV2.computed.timetableOptionsChanged.call(context)
            },
        },
        timetableCalculationResetAvailable: {
            get() {
                return TimetableV2.computed.timetableCalculationResetAvailable.call(context)
            },
        },
        selectedTimetableOptionItems: {
            get() {
                return TimetableV2.computed.selectedTimetableOptionItems.call(context)
            },
        },
        selectedCourseItems: {
            get() {
                if (selectedCourseItemsOverride !== undefined) return selectedCourseItemsOverride

                return TimetableV2.computed.selectedCourseItems.call(context)
            },
        },
        selectedAdditionalCourseItems: {
            get() {
                return TimetableV2.computed.selectedAdditionalCourseItems.call(context)
            },
        },
        selectedMissingCourseCardItems: {
            get() {
                return TimetableV2.computed.selectedMissingCourseCardItems.call(context)
            },
        },
        selectedPlannedCourseItems: {
            get() {
                return TimetableV2.computed.selectedPlannedCourseItems.call(context)
            },
        },
        selectedCourseItemsDeletable: {
            get() {
                return TimetableV2.computed.selectedCourseItemsDeletable.call(context)
            },
        },
        selectedReviewCourseItem: {
            get() {
                return TimetableV2.computed.selectedReviewCourseItem.call(context)
            },
        },
        storedTimetableV2Selection: {
            get() {
                return TimetableV2.computed.storedTimetableV2Selection.call(context)
            },
        },
        storedTimetableV2Options: {
            get() {
                return TimetableV2.computed.storedTimetableV2Options.call(context)
            },
        },
        courseSelectionOverrides: {
            get() {
                if (courseSelectionOverridesOverride !== undefined) return courseSelectionOverridesOverride

                return TimetableV2.computed.courseSelectionOverrides.call(context)
            },
        },
        storedTimetableStudentCode: {
            get() {
                return TimetableV2.computed.storedTimetableStudentCode.call(context)
            },
        },
        courseGroupsByCourseCode: {
            get() {
                return TimetableV2.computed.courseGroupsByCourseCode.call(context)
            },
        },
        offeredCourseSelectionOverrides: {
            get() {
                return TimetableV2.computed.offeredCourseSelectionOverrides.call(context)
            },
        },
        moreOfferedCourseSelectionOverrides: {
            get() {
                return TimetableV2.computed.moreOfferedCourseSelectionOverrides.call(context)
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
        moreCoursesButtonUnavailable: {
            get() {
                return TimetableV2.computed.moreCoursesButtonUnavailable.call(context)
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
        moreOfferedCourseSelectionChanged: {
            get() {
                return TimetableV2.computed.moreOfferedCourseSelectionChanged.call(context)
            },
        },
        moreCoursesSelectionChanged: {
            get() {
                return TimetableV2.computed.moreCoursesSelectionChanged.call(context)
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
    it('shows calculation action buttons and disables them while timetables are loading', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')

        expect(source).toMatch(/v-if="timetableOptionsCardVisible" class="students-timetable-v2-calculation-card__actions"[\s\S]*@click="closeTimetableOptionsCard">\s+Abbruch/u)
        expect(source).toMatch(/color="success"\s+variant="flat"\s+append-icon="mdi-check"\s+:disabled="!timetableOptionsChanged \|\| timetableCalculationLoading"\s+@click="applyTimetableOptions">\s+Anwenden/u)
        expect(source).toMatch(/v-else-if="!moreCoursesVisible" class="students-timetable-v2-calculation-card__actions"/u)
        expect(source).toMatch(/class="students-timetable-v2-calculation-card__primary-actions"[\s\S]*:color="moreCoursesButtonUnavailable \? 'error' : 'primary'"\s+variant="tonal"\s+prepend-icon="mdi-plus-circle-outline"\s+:disabled="timetableCalculationLoading \|\| moreCoursesButtonUnavailable"\s+@click="toggleMoreCoursesCard">\s+Mehr Kurse/u)
        expect(source).toMatch(/color="info"\s+variant="tonal"\s+prepend-icon="mdi-cog-outline"\s+:disabled="timetableCalculationLoading"\s+:aria-expanded="timetableOptionsCardVisible \? 'true' : 'false'"\s+@click="toggleTimetableOptionsCard">\s+Optionen/u)
        expect(source).toMatch(/color="warning"\s+variant="tonal"\s+prepend-icon="mdi-restore"\s+class="students-timetable-v2-calculation-card__reset-button"\s+:disabled="timetableCalculationLoading \|\| !timetableCalculationResetAvailable"\s+@click="resetTimetableCalculationChanges">\s+Zurücksetzen/u)
        expect(source).toContain('students-timetable-v2-card-column students-timetable-v2-selected-courses-column')
        expect(source).toMatch(/Ausgewählte Kurse[\s\S]*v-if="selectedTimetableOptionItems\.length"[\s\S]*class="students-timetable-v2-card students-timetable-v2-selected-options-card"[\s\S]*Optionen/u)
        expect(source).toContain('v-for="option in selectedTimetableOptionItems"')
        expect(source).toContain(':close-label="`Option ${option.label} entfernen`"')
        expect(source).toContain('@click:close.stop="removeSelectedTimetableOption(option)"')
        expect(source).toContain('{{ option.label }}')
        expect(source).toMatch(/v-else-if="timetableCalculationResult && !moreCoursesVisible && !timetableOptionsCardVisible"/u)
        expect(source).toMatch(/v-if="timetableOptionsCardVisible"[\s\S]*class="students-timetable-v2-options-card"[\s\S]*Optionen/u)
        expect(source).toContain(":color=\"timetableNoSaturdayDraftSelected ? 'success' : undefined\"")
        expect(source).toContain("'students-timetable-v2-options-card__option--selected': timetableNoSaturdayDraftSelected")
        expect(source).toContain(":aria-pressed=\"timetableNoSaturdayDraftSelected ? 'true' : 'false'\"")
        expect(source).toContain('@click="toggleNoSaturdayTimetableOption"')
        expect(source).toContain('Kein Samstag')
        expect(source).toContain('{{ noSaturdayTimetableCountFormatted }}')
        expect(source).toMatch(/v-else class="students-timetable-v2-calculation-card__more-course-actions"[\s\S]*Abbruch[\s\S]*Anwenden/u)
        expect(source).toMatch(/size="large"\s+color="warning"\s+variant="tonal"\s+prepend-icon="mdi-close"\s+@click="cancelMoreCoursesCard">\s+Abbruch/u)
        expect(source).toMatch(/append-icon="mdi-check"\s+:disabled="!moreCoursesSelectionChanged \|\| timetableCalculationLoading"\s+@click="applyMoreCoursesSelection">\s+Anwenden/u)
    })

    it('formats the no Saturday timetable count for the options card', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                no_saturday_timetable_count: 12,
            },
        })

        expect(context.noSaturdayTimetableCount).toBe(12)
        expect(context.noSaturdayTimetableCountFormatted).toBe('12')
    })

    it('shows selected timetable options below the selected courses card', () => {
        expect(timetableV2Context().selectedTimetableOptionItems).toEqual([])

        const context = timetableV2Context({
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: true,
        })

        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'no-saturday',
                label: 'Kein Samstag',
            },
        ])
    })

    it('stages selected timetable option removal through the pending action row', () => {
        const context = timetableV2Context({
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: true,
        })

        TimetableV2.methods.removeSelectedTimetableOption.call(context, {
            key: 'no-saturday',
        })

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.timetableNoSaturdayDraftSelected).toBe(false)
        expect(context.timetableNoSaturdaySelected).toBe(true)
        expect(context.selectedTimetableOptionItems).toEqual([])
        expect(context.moreCoursesSelectionChanged).toBe(true)
    })

    it('restores staged timetable option removal on cancel', () => {
        const context = timetableV2Context({
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: true,
        })

        TimetableV2.methods.removeSelectedTimetableOption.call(context, {
            key: 'no-saturday',
        })
        TimetableV2.methods.cancelMoreCoursesCard.call(context)

        expect(context.moreCoursesVisible).toBe(false)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(context.timetableNoSaturdaySelected).toBe(true)
        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'no-saturday',
                label: 'Kein Samstag',
            },
        ])
    })

    it('applies staged timetable option removal and recalculates', () => {
        const calculateTimetables = vi.fn()
        const context = timetableV2Context({
            calculateTimetables,
            timetableCalculationSelectedNumber: 4,
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: true,
        })

        TimetableV2.methods.removeSelectedTimetableOption.call(context, {
            key: 'no-saturday',
        })
        TimetableV2.methods.applyMoreCoursesSelection.call(context)

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.moreCoursesVisible).toBe(false)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.timetableCalculationSelectedNumber).toBe(1)
        expect(context.timetableNoSaturdayDraftSelected).toBe(false)
        expect(context.timetableNoSaturdaySelected).toBe(false)
        expect(context.selectedTimetableOptionItems).toEqual([])
        expect(payload.constraints.availableWeekdays).toEqual([1, 2, 3, 4, 5, 6])
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('marks timetable calculation reset available only when calculation state changed', () => {
        const initialTimetableV2Selection = {
            courseSelections: {
                'planned:D1': false,
            },
        }
        const context = timetableV2Context({
            initialTimetableCalculationSelectedNumber: 1,
            initialTimetableCalculationNoSaturdaySelected: false,
            initialTimetableCalculationSelection: initialTimetableV2Selection,
            storedTimetableState: {
                selection: {},
                timetableV2Selection: initialTimetableV2Selection,
                transferredStudentContext: null,
            },
            timetableCalculationSelectedNumber: 1,
            timetableCalculationVisible: true,
            timetableNoSaturdaySelected: false,
        })

        expect(context.timetableCalculationResetAvailable).toBe(false)

        context.timetableCalculationSelectedNumber = 2
        expect(context.timetableCalculationResetAvailable).toBe(true)

        context.timetableCalculationSelectedNumber = 1
        context.timetableNoSaturdaySelected = true
        expect(context.timetableCalculationResetAvailable).toBe(true)

        context.timetableNoSaturdaySelected = false
        context.storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                courseSelections: {
                    'planned:D1': false,
                    'additional:INF2': true,
                },
            },
            transferredStudentContext: null,
        }
        expect(context.timetableCalculationResetAvailable).toBe(true)
    })

    it('does not reset timetable calculation changes when there is nothing to reset', () => {
        const calculateTimetables = vi.fn()
        const saveStoredTimetableState = vi.fn()
        const initialTimetableV2Selection = {
            courseSelections: {
                'planned:D1': false,
            },
        }
        const context = timetableV2Context({
            calculateTimetables,
            initialTimetableCalculationSelectedNumber: 1,
            initialTimetableCalculationNoSaturdaySelected: false,
            initialTimetableCalculationSelection: initialTimetableV2Selection,
            saveStoredTimetableState,
            storedTimetableState: {
                selection: {},
                timetableV2Selection: initialTimetableV2Selection,
                transferredStudentContext: null,
            },
            timetableCalculationSelectedNumber: 1,
            timetableCalculationVisible: true,
            timetableNoSaturdaySelected: false,
        })

        TimetableV2.methods.resetTimetableCalculationChanges.call(context)

        expect(context.timetableCalculationResetAvailable).toBe(false)
        expect(saveStoredTimetableState).not.toHaveBeenCalled()
        expect(calculateTimetables).not.toHaveBeenCalled()
    })

    it('resets timetable calculation course changes to the initial calculation state', () => {
        let context: ReturnType<typeof timetableV2Context>
        const calculateTimetables = vi.fn()
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })
        const initialTimetableV2Selection = {
            courseSelections: {
                'planned:D1': false,
                'additional:INF2': true,
            },
            offeredCourseSelections: {
                'planned:D1::D1-A': false,
            },
        }

        context = timetableV2Context({
            calculateTimetables,
            initialTimetableCalculationSelectedNumber: 1,
            initialTimetableCalculationNoSaturdaySelected: false,
            initialTimetableCalculationSelection: initialTimetableV2Selection,
            moreCourseAvailabilityByKey: {
                'additional:INF2': false,
            },
            moreCourseAvailabilitySignature: 'changed',
            moreCoursesVisible: true,
            moreCoursesCardVisible: true,
            saveStoredTimetableState,
            selectedMoreCourseKey: 'additional:INF2',
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'planned:D1': false,
                        'additional:INF2': true,
                        'additional:GS1': true,
                    },
                    offeredCourseSelections: {
                        'planned:D1::D1-A': false,
                        'additional:GS1::GS1-A': false,
                    },
                    moreOfferedCourseSelections: {
                        'additional:GS1::GS1-B': true,
                    },
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationSelectedNumber: 4,
            timetableCalculationVisible: true,
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: true,
            timetableOptionsCardVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.resetTimetableCalculationChanges.call(context)

        expect(saveStoredTimetableState).toHaveBeenCalledWith({
            selection: {},
            timetableV2Selection: initialTimetableV2Selection,
            timetableV2Options: {
                noSaturday: false,
            },
            transferredStudentContext: null,
        })
        expect(context.moreCoursesVisible).toBe(false)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.timetableOptionsCardVisible).toBe(false)
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.moreCourseAvailabilityByKey).toEqual({})
        expect(context.timetableCalculationSelectedNumber).toBe(1)
        expect(context.timetableNoSaturdayDraftSelected).toBe(false)
        expect(context.timetableNoSaturdaySelected).toBe(false)
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('does not render checkbox controls in the more courses chips', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')

        expect(source).not.toContain('<v-checkbox-btn')
        expect(source).toMatch(/:color="moreCourseChipColor\(course\)"\s+:variant="selectedMoreCourseItem\?\.selectionKey === course\.selectionKey && !moreCourseUnavailable\(course\) \? 'flat' : 'tonal'"\s+:disabled="moreCourseDisabled\(course\)"/u)
        expect(source).toContain("'students-timetable-v2-selected-courses-card__course--active': selectedMoreCourseItem?.selectionKey === course.selectionKey")
        expect(source).toContain("'students-timetable-v2-selected-courses-card__course--offered-deselected': moreCourseOfferedCourseItemsAllDeselected(course)")
        expect(source).toContain("'students-timetable-v2-more-courses-card__course--unavailable': moreCourseUnavailable(course)")
        expect(source).toMatch(/@click="toggleMoreCourseOffers\(course\)"/u)
    })

    it('lists only courses that are not already selected in the more courses card', async () => {
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
            { courseGroupLabel: 'Vorgesehen', label: 'GW1', meta: '4 Std.' },
            { courseGroupLabel: 'Zusätzlich', label: 'GS1', meta: '4 Std.' },
        ])

        await TimetableV2.methods.toggleMoreCoursesCard.call(context)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreOfferedCourseSelectionChanged).toBe(false)
        const defaultAdditionalCourse = context.moreCoursesCardItems.find((course) => course.label === 'GS1')

        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAllDeselected.call(context, defaultAdditionalCourse)).toBe(true)

        TimetableV2.methods.toggleMoreCourseOffers.call(context, defaultAdditionalCourse)

        const defaultAdditionalOfferedCourse = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, defaultAdditionalCourse)[0]

        expect(context.selectedMoreCourseItem?.label).toBe('GS1')
        expect(context.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                moreOfferedCourseSelections: {
                    [defaultAdditionalOfferedCourse.selectionKey]: true,
                },
            }),
        }))

        context.storedTimetableState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        expect(context.moreOfferedCourseSelectionChanged).toBe(true)
        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAnySelected.call(context, defaultAdditionalCourse)).toBe(true)
        expect(TimetableV2.methods.moreCourseDisabled.call(context, context.moreCoursesCardItems[0])).toBe(true)

        const saveCallCount = context.saveStoredTimetableState.mock.calls.length

        TimetableV2.methods.toggleMoreCourseOffers.call(context, defaultAdditionalCourse)

        expect(context.selectedMoreCourseItem?.label).toBe('GS1')
        expect(context.saveStoredTimetableState).toHaveBeenCalledTimes(saveCallCount)

        TimetableV2.methods.toggleMoreCourseOffers.call(context, context.moreCoursesCardItems[0])

        expect(context.selectedMoreCourseItem?.label).toBe('GS1')
        expect(context.saveStoredTimetableState).toHaveBeenCalledTimes(saveCallCount)

        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, defaultAdditionalOfferedCourse)

        context.storedTimetableState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAllDeselected.call(context, defaultAdditionalCourse)).toBe(true)
        expect(TimetableV2.methods.moreCourseDisabled.call(context, context.moreCoursesCardItems[0])).toBe(false)

        TimetableV2.methods.toggleMoreCourseOffers.call(context, context.moreCoursesCardItems[0])

        const plannedOfferedCourse = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, context.moreCoursesCardItems[0])[0]

        expect(context.selectedMoreCourseItem?.label).toBe('GW1')
        expect(context.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                moreOfferedCourseSelections: {
                    [plannedOfferedCourse.selectionKey]: true,
                },
            }),
        }))

        context.storedTimetableState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        TimetableV2.methods.cancelMoreCoursesCard.call(context)

        const restoredState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        expect(restoredState.timetableV2Selection.moreOfferedCourseSelections).toBeUndefined()
        expect(context.moreCoursesVisible).toBe(false)
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.moreOfferedCourseSelectionSnapshot).toBe('')
        expect(context.moreOfferedCourseSelectionSnapshotSelections).toEqual({})
    })

    it('marks impossible more courses red and prevents opening their offers', () => {
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
            ],
            moreCoursesVisible: true,
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
        })
        const moreCourse = context.moreCoursesCardItems[0]

        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('success')

        TimetableV2.methods.setMoreCourseAvailability.call(context, moreCourse.selectionKey, false)

        expect(TimetableV2.methods.moreCourseUnavailable.call(context, moreCourse)).toBe(true)
        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('error')

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        expect(context.selectedMoreCourseKey).toBe('')
    })

    it('marks the more courses button unavailable when every more course is impossible', () => {
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
                { class_name: 'GS1-A', course: 'GS1', hour: 3, title: 'GS1', weekday: 3 },
            ],
            moreCourseAvailabilitySignature: '',
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
                { code: 'GS1', hours: 4, key: 'GS1', label: 'GS1' },
            ],
        })
        context.moreCourseAvailabilitySignature = TimetableV2.methods.moreCourseAvailabilityCurrentSignature.call(context)

        expect(context.moreCoursesButtonUnavailable).toBe(false)

        context.moreCourseAvailabilityByKey = Object.fromEntries(
            context.moreCoursesCardItems.map((course) => [course.selectionKey, false])
        )

        expect(context.moreCoursesButtonUnavailable).toBe(true)

        context.moreCourseAvailabilityByKey[context.moreCoursesCardItems[0].selectionKey] = true

        expect(context.moreCoursesButtonUnavailable).toBe(false)
    })

    it('loads more course availability from valid timetable counts', async () => {
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
            ],
            moreCourseAvailabilityRequestId: 1,
            requestMoreCourseAvailability: vi.fn(() => Promise.resolve({
                data: {
                    data: {
                        conflict_timetable_count: 5,
                        full_green_timetable_count: 0,
                        green_timetable_count: 0,
                    },
                },
            })),
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
        })
        const moreCourse = context.moreCoursesCardItems[0]

        await TimetableV2.methods.loadMoreCourseAvailabilityForCourse.call(context, moreCourse, 1)

        expect(context.requestMoreCourseAvailability).toHaveBeenCalledWith(moreCourse)
        expect(context.moreCourseAvailabilityByKey).toEqual({
            [moreCourse.selectionKey]: false,
        })
    })

    it('keeps more course items hidden until availability checks finish', async () => {
        let resolveAvailabilityRequest: (value: unknown) => void = () => {}
        const availabilityRequest = new Promise((resolve) => {
            resolveAvailabilityRequest = resolve
        })
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
            ],
            requestMoreCourseAvailability: vi.fn(() => availabilityRequest),
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
        })
        const moreCourse = context.moreCoursesCardItems[0]

        const loadingRequest = TimetableV2.methods.openMoreCoursesCard.call(context)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCourseAvailabilityLoading).toBe(true)
        expect(context.moreCourseAvailabilityByKey).toEqual({})
        expect(TimetableV2.methods.moreCourseDisabled.call(context, moreCourse)).toBe(true)

        resolveAvailabilityRequest({
            data: {
                data: {
                    full_green_timetable_count: 1,
                    green_timetable_count: 0,
                },
            },
        })
        await loadingRequest

        expect(context.moreCourseAvailabilityLoading).toBe(false)
        expect(context.moreCourseAvailabilityByKey).toEqual({
            [moreCourse.selectionKey]: true,
        })
        expect(TimetableV2.methods.moreCourseDisabled.call(context, moreCourse)).toBe(false)
    })

    it('starts more course availability checks immediately after calculating timetables', async () => {
        const requestMoreCourseAvailability = vi.fn(() => Promise.resolve({
            data: {
                data: {
                    full_green_timetable_count: 2,
                    green_timetable_count: 0,
                },
            },
        }))
        const context = timetableV2Context({
            calculateTimetables: TimetableV2.methods.calculateTimetables,
            courseGroups: [
                { class_name: 'D1-A', course: 'D1', hour: 1, title: 'D1', weekday: 1 },
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
            ],
            moreCourseAvailabilityByKey: {
                old: false,
            },
            moreCourseAvailabilitySignature: 'old',
            requestMoreCourseAvailability,
            requestTimetableV2Calculation: vi.fn(() => Promise.resolve({
                data: {
                    data: {
                        full_green_timetable_count: 5,
                        green_timetable_count: 0,
                        selected_timetable: { number: 1, type: 'full_green' },
                    },
                },
            })),
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'D1', selectionKey: 'planned:D1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
            timetableV2Step: 'timetable-calculation',
        })
        const moreCourse = context.moreCoursesCardItems[0]

        await TimetableV2.methods.calculateTimetables.call(context)
        await new Promise((resolve) => {
            setTimeout(resolve, 0)
        })

        expect(requestMoreCourseAvailability).toHaveBeenCalledWith(moreCourse)
        expect(context.moreCourseAvailabilityLoading).toBe(false)
        expect(context.moreCourseAvailabilityByKey).toEqual({
            [moreCourse.selectionKey]: true,
        })
    })

    it('reuses completed background more course availability when opening the card', async () => {
        const requestMoreCourseAvailability = vi.fn()
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
            ],
            requestMoreCourseAvailability,
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
        })
        const moreCourse = context.moreCoursesCardItems[0]
        context.moreCourseAvailabilitySignature = TimetableV2.methods.moreCourseAvailabilityCurrentSignature.call(context)
        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: true,
        }

        await TimetableV2.methods.openMoreCoursesCard.call(context)

        expect(requestMoreCourseAvailability).not.toHaveBeenCalled()
        expect(context.moreCourseAvailabilityLoading).toBe(false)
        expect(context.moreCourseAvailabilityByKey).toEqual({
            [moreCourse.selectionKey]: true,
        })
    })

    it('restores the previous more course offered selections when cancelling', () => {
        const context = timetableV2Context({
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    moreOfferedCourseSelections: {
                        'planned:GW1:old-offer': true,
                    },
                },
                transferredStudentContext: null,
            },
        })

        TimetableV2.methods.toggleMoreCoursesCard.call(context)

        expect(context.moreOfferedCourseSelectionChanged).toBe(false)

        TimetableV2.methods.saveMoreOfferedCourseSelections.call(context, {
            ...context.moreOfferedCourseSelectionOverrides,
            'planned:GW1:new-offer': true,
        })

        context.storedTimetableState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        expect(context.moreOfferedCourseSelectionChanged).toBe(true)

        TimetableV2.methods.cancelMoreCoursesCard.call(context)

        expect(context.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                moreOfferedCourseSelections: {
                    'planned:GW1:old-offer': true,
                },
            }),
        }))
    })

    it('applies more course selection changes by closing the card and recalculating', () => {
        const calculateTimetables = vi.fn()
        const context = timetableV2Context({
            calculateTimetables,
            timetableCalculationSelectedNumber: 3,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.toggleMoreCoursesCard.call(context)

        context.storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                moreOfferedCourseSelections: {
                    'additional:INF2::INF2-A': true,
                },
            },
            transferredStudentContext: null,
        }

        TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(context.moreCoursesVisible).toBe(false)
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.moreOfferedCourseSelectionSnapshot).toBe('')
        expect(context.moreOfferedCourseSelectionSnapshotSelections).toEqual({})
        expect(context.timetableCalculationSelectedNumber).toBe(1)
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('moves a removed selected course into more courses and restores it on cancel', () => {
        let context: ReturnType<typeof timetableV2Context>
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            saveStoredTimetableState,
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, key: 'D1', label: 'D1' },
                { code: 'GW1', hours: 4, key: 'GW1', label: 'GW1' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationVisible: true,
        })
        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(context, context.storedPlannedCourseItems[0], 'planned')

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'planned:D1': false,
        })
        expect(context.moreCoursesCardItems.map((course) => course.label)).toContain('D1')

        TimetableV2.methods.cancelMoreCoursesCard.call(context)

        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toBeUndefined()
        expect(context.moreCoursesVisible).toBe(false)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.moreCoursesCardItems.map((course) => course.label)).not.toContain('D1')
    })

    it('applies removed selected courses by recalculating the timetable', () => {
        let context: ReturnType<typeof timetableV2Context>
        const calculateTimetables = vi.fn()
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables,
            saveStoredTimetableState,
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'additional:INF2': true,
                    },
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationSelectedNumber: 5,
            timetableCalculationVisible: true,
        })
        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(context, context.storedAdditionalCourseItems[0], 'additional')

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)
        TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toBeUndefined()
        expect(context.moreCoursesVisible).toBe(false)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.timetableCalculationSelectedNumber).toBe(1)
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('promotes selected more courses before recalculating', () => {
        let context: ReturnType<typeof timetableV2Context>
        const calculateTimetables = vi.fn()
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables,
            courseGroups: [
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
                { class_name: 'INF2-B', course: 'INF2', hour: 3, title: 'INF2', weekday: 3 },
            ],
            saveStoredTimetableState,
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'D1', selectionKey: 'planned:D1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationSelectedNumber: 4,
            timetableV2Step: 'timetable-calculation',
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'INF2')
        const [selectedOffer, deselectedOffer] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
        context.selectedMoreCourseKey = moreCourse.selectionKey

        context.storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                moreOfferedCourseSelections: {
                    [selectedOffer.selectionKey]: true,
                },
            },
            transferredStudentContext: null,
        }

        TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                courseSelections: {
                    'additional:INF2': true,
                },
                offeredCourseSelections: {
                    [deselectedOffer.selectionKey]: false,
                },
            }),
        }))
        expect(context.storedTimetableState.timetableV2Selection.moreOfferedCourseSelections).toBeUndefined()
        expect(context.moreCoursesVisible).toBe(false)
        expect(context.timetableCalculationSelectedNumber).toBe(1)
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('sends an applied more course in the recalculation request', async () => {
        let context: ReturnType<typeof timetableV2Context>
        let requestPayload
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables: TimetableV2.methods.calculateTimetables,
            courseGroups: [
                { class_name: 'D1-A', course: 'D1', hour: 1, title: 'D1', weekday: 1 },
                { class_name: 'GS1-A', course: 'GS1', hour: 2, title: 'GS1', weekday: 2 },
            ],
            requestTimetableV2Calculation: vi.fn((options = {}) => {
                requestPayload = TimetableV2.methods.timetableV2CalculationPayload.call(context, options)

                return Promise.resolve({
                    data: {
                        data: {
                            full_green_timetable_count: 42,
                            green_timetable_count: 3,
                            selected_timetable: { number: 1, type: 'full_green' },
                        },
                    },
                })
            }),
            saveStoredTimetableState,
            storedAdditionalCourseItems: [
                { code: 'GS1', hours: 4, key: 'GS1', label: 'GS1' },
            ],
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, key: 'D1', label: 'D1' },
            ],
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationResult: {
                full_green_timetable_count: 1350,
                green_timetable_count: 0,
                selected_timetable: { number: 1, type: 'full_green' },
            },
            timetableV2Step: 'timetable-calculation',
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'GS1')

        await TimetableV2.methods.openMoreCoursesCard.call(context)
        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)
        context.storedTimetableState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        await TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(context.selectedCourseItems.map((course) => course.key)).toContain('GS1')
        expect(requestPayload.selected_course_keys).toEqual(['D1'])
        expect(requestPayload.selected_additional_course_keys).toEqual(['GS1'])
        expect(requestPayload.selected_additional_courses_required).toBe(true)
        expect(context.timetableCalculationResult.full_green_timetable_count).toBe(42)
    })

    it('ignores unavailable more courses when calculating selected courses', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'D1', selectionKey: 'planned:D1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
            courseGroups: [
                { class_name: 'D1-A', course: 'D1', hour: 1, title: 'D1', weekday: 1 },
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
            ],
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'INF2')
        const [selectedOffer] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
        context.selectedMoreCourseKey = moreCourse.selectionKey

        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: false,
        }
        context.storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                moreOfferedCourseSelections: {
                    [selectedOffer.selectionKey]: true,
                },
            },
            transferredStudentContext: null,
        }

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.selected_course_keys).toEqual(['D1'])
        expect(payload.selected_additional_course_keys).toEqual([])
        expect(payload.selected_additional_courses_required).toBe(false)
    })

    it('does not promote unavailable more courses before recalculating', () => {
        let context: ReturnType<typeof timetableV2Context>
        let payload
        const calculateTimetables = vi.fn(() => {
            payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

            return Promise.resolve()
        })
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables,
            courseGroups: [
                { class_name: 'D1-A', course: 'D1', hour: 1, title: 'D1', weekday: 1 },
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
            ],
            saveStoredTimetableState,
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'D1', selectionKey: 'planned:D1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
            timetableCalculationSelectedNumber: 4,
            timetableV2Step: 'timetable-calculation',
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'INF2')
        const [selectedOffer] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
        context.selectedMoreCourseKey = moreCourse.selectionKey

        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: false,
        }
        context.storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                moreOfferedCourseSelections: {
                    [selectedOffer.selectionKey]: true,
                },
            },
            transferredStudentContext: null,
        }

        TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(saveStoredTimetableState).not.toHaveBeenCalled()
        expect(payload.selected_course_keys).toEqual(['D1'])
        expect(payload.selected_additional_course_keys).toEqual([])
        expect(payload.selected_additional_courses_required).toBe(false)
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('indexes offered course groups by normalized course code', () => {
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'GW1-1C-HOF', course: 'GW1', hour: 12, title: 'GW1', weekday: 3 },
                { class_name: 'GS1-2A-PLOC', course: 'GS1', hour: 13, title: 'GS1', weekday: 4 },
            ],
        })

        expect(context.courseGroupsByCourseCode.get('GW1')).toHaveLength(1)
        expect(TimetableV2.methods.courseGroupsForCourseAliases.call(context, ['GW1'])).toEqual([
            context.courseGroups[0],
        ])
        expect(TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, { code: 'GW1' }))
            .toHaveLength(1)
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

    it('opens course review without pre-selecting the first selected course', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { label: 'D1', selectionKey: 'planned:D1' },
                { label: 'GW1', selectionKey: 'planned:GW1' },
            ],
        })

        TimetableV2.methods.openCourseReview.call(context)

        expect(context.timetableV2Step).toBe('course-review')
        expect(context.selectedReviewCourseKey).toBe('')
        expect(context.selectedReviewCourseItem).toBeNull()
        expect(context.loadCourseGroups).toHaveBeenCalledOnce()
        expect(context.loadSchoolHours).toHaveBeenCalledOnce()
    })

    it('restores course review without pre-selecting the first selected course', () => {
        const context = timetableV2Context({
            courseReviewVisible: true,
            selectedCourseItems: [
                { label: 'D1', selectionKey: 'planned:D1' },
                { label: 'GW1', selectionKey: 'planned:GW1' },
            ],
        })

        TimetableV2.methods.restoreTimetableV2RouteStepEffects.call(context)

        expect(context.selectedReviewCourseKey).toBe('')
        expect(context.selectedReviewCourseItem).toBeNull()
        expect(context.loadCourseGroups).toHaveBeenCalledOnce()
        expect(context.loadSchoolHours).toHaveBeenCalledOnce()
    })

    it('selects a review course only after the course chip is clicked', () => {
        const context = timetableV2Context({
            courseReviewVisible: true,
            selectedCourseItems: [
                { label: 'D1', selectionKey: 'planned:D1' },
                { label: 'GW1', selectionKey: 'planned:GW1' },
            ],
        })

        TimetableV2.methods.selectReviewCourse.call(context, context.selectedCourseItems[1])

        expect(context.selectedReviewCourseKey).toBe('planned:GW1')
        expect(context.selectedReviewCourseItem).toEqual(context.selectedCourseItems[1])
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

    it('does not preload review-only data while mounted on the selection step', () => {
        const context = timetableV2Context({
            loadSubjectRows: vi.fn(() => Promise.resolve()),
        })

        TimetableV2.mounted.call(context)

        expect(context.loadSubjectRows).toHaveBeenCalledOnce()
        expect(context.loadStoredStudentOverview).toHaveBeenCalledWith(null)
        expect(context.loadCourseGroups).not.toHaveBeenCalled()
        expect(context.loadSchoolHours).not.toHaveBeenCalled()
    })

    it('keeps review-only loaders out of the selection page loading state', () => {
        const context = timetableV2Context({
            courseGroupsLoading: true,
            schoolHoursLoading: true,
        })

        expect(TimetableV2.computed.timetableV2PageLoading.call(context)).toBe(false)

        context.courseReviewVisible = true

        expect(TimetableV2.computed.timetableV2PageLoading.call(context)).toBe(true)
    })

    it('loads only subject rows from the overview settings endpoint', async () => {
        const axiosMock = {
            get: vi.fn().mockResolvedValueOnce({
                data: {
                    data: {
                        subjects: [
                            { json_code: 'D1', name: 'Deutsch 1' },
                        ],
                    },
                },
            }),
        }
        globalThis.axios = axiosMock

        const context = timetableV2Context({
            applyCourseLimitPreselection: vi.fn(),
            loadSubjectRows: TimetableV2.methods.loadSubjectRows,
        })

        await TimetableV2.methods.loadSubjectRows.call(context)

        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/students-timetables/subjects-overview-settings', {
            params: {
                subjects_only: 1,
            },
        })
        expect(context.subjectRows).toEqual([
            { json_code: 'D1', name: 'Deutsch 1' },
        ])
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
        expect(payload.selected_additional_courses_required).toBe(true)
        expect(payload.deselected_course_group_keys).toEqual(['INF1|A', 'INF1|B'])
    })

    it('calculates timetables with selected more courses and only their checked offered courses', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'D1', selectionKey: 'planned:D1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
            courseGroups: [
                { class_name: 'D1-A', course: 'D1', hour: 1, title: 'D1', weekday: 1 },
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
                { class_name: 'INF2-B', course: 'INF2', hour: 3, title: 'INF2', weekday: 3 },
            ],
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'INF2')
        const [selectedOffer, deselectedOffer] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
        context.selectedMoreCourseKey = moreCourse.selectionKey

        context.storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                moreOfferedCourseSelections: {
                    [selectedOffer.selectionKey]: true,
                },
            },
            transferredStudentContext: null,
        }

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.selected_course_keys).toEqual(['D1'])
        expect(payload.selected_additional_course_keys).toEqual(['INF2'])
        expect(payload.selected_additional_courses_required).toBe(true)
        expect(payload.deselected_course_group_keys).toEqual([deselectedOffer.backendSelectionKey])
    })

    it('checks more course availability by requiring the candidate course', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'D1', selectionKey: 'planned:D1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'INF2')

        const payload = TimetableV2.methods.timetableV2CalculationPayloadForMoreCourseAvailability.call(context, moreCourse)

        expect(payload.selected_course_keys).toEqual(['D1'])
        expect(payload.selected_additional_course_keys).toEqual(['INF2'])
        expect(payload.selected_additional_courses_required).toBe(true)
        expect(payload.selected_timetable_type).toBe('full_green')
        expect(payload.selected_timetable_number).toBe(1)
    })

    it('applies the no Saturday option to timetable and more course availability payloads', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'D1', selectionKey: 'planned:D1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
            timetableNoSaturdaySelected: true,
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'INF2')

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)
        const moreCoursePayload = TimetableV2.methods.timetableV2CalculationPayloadForMoreCourseAvailability.call(
            context,
            moreCourse,
        )

        expect(payload.constraints.availableWeekdays).toEqual([1, 2, 3, 4, 5])
        expect(moreCoursePayload.constraints.availableWeekdays).toEqual([1, 2, 3, 4, 5])
    })

    it('stages the no Saturday option without recalculating timetables', () => {
        const calculateTimetables = vi.fn()
        const context = timetableV2Context({
            calculateTimetables,
            moreCourseAvailabilityByKey: {
                'additional:INF2': true,
            },
            moreCourseAvailabilitySignature: 'previous',
            timetableCalculationSelectedNumber: 4,
            timetableNoSaturdayDraftSelected: false,
            timetableNoSaturdaySelected: false,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.toggleNoSaturdayTimetableOption.call(context)

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(context.timetableNoSaturdaySelected).toBe(false)
        expect(context.timetableOptionsChanged).toBe(true)
        expect(context.timetableCalculationSelectedNumber).toBe(4)
        expect(context.moreCourseAvailabilityByKey).toEqual({
            'additional:INF2': true,
        })
        expect(context.moreCourseAvailabilitySignature).toBe('previous')
        expect(payload.constraints.availableWeekdays).toEqual([1, 2, 3, 4, 5, 6])
        expect(calculateTimetables).not.toHaveBeenCalled()
    })

    it('applies staged timetable options and recalculates more course availability', () => {
        const calculateTimetables = vi.fn()
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            calculateTimetables,
            moreCourseAvailabilityByKey: {
                'additional:INF2': true,
            },
            moreCourseAvailabilitySignature: 'previous',
            saveStoredTimetableState,
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                timetableV2Options: {
                    noSaturday: false,
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationSelectedNumber: 4,
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: false,
            timetableOptionsCardVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.applyTimetableOptions.call(context)

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(context.timetableNoSaturdaySelected).toBe(true)
        expect(context.timetableOptionsChanged).toBe(false)
        expect(context.timetableOptionsCardVisible).toBe(false)
        expect(context.timetableCalculationSelectedNumber).toBe(1)
        expect(context.moreCourseAvailabilityByKey).toEqual({})
        expect(context.moreCourseAvailabilitySignature).toBe('')
        expect(payload.constraints.availableWeekdays).toEqual([1, 2, 3, 4, 5])
        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Options: {
                noSaturday: true,
            },
        }))
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('restores stored timetable options after a page refresh', () => {
        const context = timetableV2Context({
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                timetableV2Options: {
                    noSaturday: true,
                },
                transferredStudentContext: null,
            },
            timetableNoSaturdayDraftSelected: false,
            timetableNoSaturdaySelected: false,
        })

        TimetableV2.methods.syncStoredTimetableOptions.call(context)

        expect(context.timetableNoSaturdaySelected).toBe(true)
        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'no-saturday',
                label: 'Kein Samstag',
            },
        ])
    })

    it('does not require additional courses when none are selected for calculation', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'D1', selectionKey: 'planned:D1' },
            ],
        })
        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.selected_additional_course_keys).toEqual([])
        expect(payload.selected_additional_courses_required).toBe(false)
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
        expect(TimetableV2.methods.selectedTimetableV2SlotTimePatternLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['1-1'].conflicts[0], { showRegularRange: true })).toBe('3-wöchig: 21.02.-18.4.')
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
        expect(TimetableV2.methods.selectedTimetableV2OccasionalOverlapChips.call(
            context,
            {
                code: 'GWB1',
                sourceLabel: 'GWB1-1C-HÖF',
                courseGroup: { dates: ['2026-02-18'], weekday: 2, hour: 1 },
                isOccasional: true,
                conflicts: [
                    {
                        code: 'GW1',
                        sourceLabel: 'GWB1-1C-HÖF',
                        courseGroup: { weekday: 2, hour: 1 },
                    },
                    {
                        code: 'LPT',
                        sourceLabel: 'LPT-1CK-DREI',
                        courseGroup: { dates: ['2026-02-18'], weekday: 2, hour: 1 },
                        isOccasional: true,
                    },
                ],
            },
        )).toEqual([
            { key: 'GWB1|GWB1-1C-HÖF', label: 'GWB1-1C-HÖF 18.02.' },
            { key: 'LPT|LPT-1CK-DREI', label: 'LPT-1CK-DREI 18.02.' },
        ])
        const oneDayLptAgainstRegularM1 = {
            code: 'LPT',
            sourceLabel: 'LPT-1CK-DREI',
            courseGroup: { dates: ['2026-02-18'], weekday: 2, hour: 1 },
            conflicts: [
                {
                    code: 'M1',
                    sourceLabel: 'M1-1C-MAY',
                    courseGroup: {
                        dates: ['2026-02-18', '2026-02-25', '2026-03-04'],
                        weekday: 2,
                        hour: 1,
                    },
                },
            ],
        }

        expect(TimetableV2.methods.selectedTimetableV2SlotTitle.call(
            context,
            TimetableV2.methods.selectedTimetableV2DisplaySlot.call(context, oneDayLptAgainstRegularM1),
        )).toBe('M 1')
        expect(TimetableV2.methods.selectedTimetableV2OccasionalOverlapChips.call(
            context,
            oneDayLptAgainstRegularM1,
        )).toEqual([
            { key: 'LPT|LPT-1CK-DREI', label: 'LPT-1CK-DREI 18.02.' },
        ])
        expect(TimetableV2.methods.selectedTimetableV2OccasionalOverlapChips.call(
            context,
            {
                code: 'GW1',
                sourceLabel: 'GWB1-1C-HÖF',
                courseGroup: { dates: ['2026-02-18'], weekday: 2, hour: 1 },
                isOccasional: true,
                conflicts: [
                    {
                        code: 'LPT',
                        sourceLabel: 'LPT-1CK-DREI',
                        courseGroup: { dates: ['2026-02-18'], weekday: 2, hour: 1 },
                        isOccasional: true,
                    },
                ],
            },
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
