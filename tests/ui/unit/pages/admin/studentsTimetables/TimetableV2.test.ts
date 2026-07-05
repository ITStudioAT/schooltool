import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import CourseSelectionCards from '@/pages/admin/studentsTimetables/timetableV2/CourseSelectionCards.vue'
import TimetableV2 from '@/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue'

function timetableV2Context(overrides = {}) {
    const courseSelectionOverridesOverride = overrides.courseSelectionOverrides
    const storedCompletedCourseItemsOverride = overrides.storedCompletedCourseItems
    const storedMissingCourseCardItemsOverride = overrides.storedMissingCourseCardItems
    const storedSemesterCourseItemsOverride = overrides.storedSemesterCourseItems
    const storedPlannedCourseItemsOverride = overrides.storedPlannedCourseItems
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
        robotStudents: [],
        initialRouteLoading: false,
        schoolHours: [],
        subjectRows: [],
        studentCompletedCoursesLoading: false,
        selectionBootstrapLoading: false,
        studentCompletedCoursesError: '',
        studentOverviewActiveRequestKey: '',
        studentOverviewLoadedRequestKey: '',
        studentCompletedCoursesRequestId: 0,
        subjectRowsLoading: false,
        courseGroupsLoading: false,
        courseGroupsLoaded: false,
        courseGroups: [],
        courseGroupsRevision: 0,
        courseGroupsError: '',
        offeredCourseItemsCache: {},
        offeredCourseItemsCacheCourseGroups: null,
        subjectRowsError: '',
        schoolHoursLoading: false,
        storageRevision: 0,
        storedTimetableStudentContext: null,
        storedTimetableState: null,
        pendingStoredTimetableState: null,
        pendingRemovedSelectedCourseKeys: {},
        draftCourseSelections: null,
        courseSelectionDraftPending: false,
        storedTimetableStateSaveRequestId: 0,
        storedTimetableStateSaveTimer: null,
        storedTimetableStateSaving: false,
        storedAdditionalCourseItems: [],
        storedMissingCourseCardItems: [],
        storedSemesterCourseItems: [],
        storedPlannedCourseItems: [],
        noStudentSelectedSemester: 1,
        selectedCourseItems: [],
        selectedReviewCourseKey: '',
        timetableCalculationError: '',
        timetableCalculationLoading: false,
        timetableCalculationLoadingMode: 'calculation',
        timetableCalculationProgress: 0,
        timetableCalculationProgressCompletionPending: false,
        timetableCalculationProgressResetTimer: null,
        timetableCalculationProgressSource: '',
        timetableCalculationProgressTimer: null,
        timetableCalculationRequestId: 0,
        timetableCalculationResult: null,
        timetableCalculationResultCache: {},
        adoptedTimetableCalculationResult: null,
        adoptedTimetableSelectedNumber: 1,
        adoptedTimetableCalculationSelectionSnapshot: null,
        adoptedTimetableCalculationOptionsSnapshot: null,
        adoptedTimetableSelectionSnapshot: null,
        adoptedTimetableStudentContextSnapshot: null,
        printDialogVisible: false,
        printOptions: {
            singleWeeks: false,
            courseList: true,
            courseOverview: true,
        },
        pdfExporting: false,
        publishedTimetableSaving: false,
        adoptedPublishedTimetableReport: {
            type: 'success',
            message: '',
        },
        timetableCalculationNumberDraft: '1',
        timetableCalculationSelectedNumber: 1,
        initialTimetableCalculationSelectedNumber: 1,
        initialTimetableCalculationSelection: null,
        initialTimetableCalculationNoSaturdaySelected: false,
        initialTimetableCalculationMaxFreeDaysSelected: false,
        initialTimetableCalculationNoDistanceLearningSelected: false,
        initialTimetableCalculationStartsFromPeriod10Selected: false,
        timetableNoSaturdayDraftSelected: false,
        timetableNoSaturdaySelected: false,
        timetableMaxFreeDaysDraftSelected: false,
        timetableMaxFreeDaysSelected: false,
        timetableNoDistanceLearningDraftSelected: false,
        timetableNoDistanceLearningSelected: false,
        timetableStartsFromPeriod10DraftSelected: false,
        timetableStartsFromPeriod10Selected: false,
        timetableOptionsCardVisible: false,
        moreCoursesVisible: false,
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
        restorableMoreCourseAvailabilitySignatures: {},
        timetableQualityCountersLoading: false,
        timetableQualityCountersRequestId: 0,
        conflictResolutionRecommendationByKey: {},
        conflictResolutionRecommendationLoading: false,
        conflictResolutionRecommendationPromise: null,
        conflictResolutionRecommendationRequestId: 0,
        conflictResolutionRecommendationSignature: '',
        activeMoreExistingCourseBaseKey: '',
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
        requestMoreCourseAvailability: vi.fn((courses = [], options = {}) => {
            const candidateCourses = Array.isArray(courses) ? courses : [courses]
            const availability = Object.fromEntries(candidateCourses
                .flatMap((course) => TimetableV2.methods.moreCourseAvailabilityPayloadCandidates.call(context, course, options))
                .map((course) => [course.availability_key, { available: true, valid_timetable_count: 1 }])
                .filter(([availabilityKey]) => availabilityKey))

            return Promise.resolve({
                data: {
                    data: {
                        availability,
                    },
                },
            })
        }),
        requestTimetableV2QualityCounters: vi.fn(() => Promise.resolve({
            data: {
                data: {
                    all_quality_criteria_count: 0,
                    quality_counters: [],
                    selected_quality_criteria_count: 0,
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
        currentTimetableV2CalculationResult: {
            get() {
                return TimetableV2.computed.currentTimetableV2CalculationResult.call(context)
            },
        },
        adoptedTimetableVisible: {
            get() {
                return TimetableV2.computed.adoptedTimetableVisible.call(context)
            },
        },
        timetableV2PageLoading: {
            get() {
                return TimetableV2.computed.timetableV2PageLoading.call(context)
            },
        },
        adoptedTimetableNumberLabel: {
            get() {
                return TimetableV2.computed.adoptedTimetableNumberLabel.call(context)
            },
        },
        timetableCalculationCardTitleLabel: {
            get() {
                return TimetableV2.computed.timetableCalculationCardTitleLabel.call(context)
            },
        },
        courseReviewStudentLabel: {
            get() {
                return TimetableV2.computed.courseReviewStudentLabel.call(context)
            },
        },
        courseReviewStudentCode: {
            get() {
                return TimetableV2.computed.courseReviewStudentCode.call(context)
            },
        },
        courseReviewStudentEmail: {
            get() {
                return TimetableV2.computed.courseReviewStudentEmail.call(context)
            },
        },
        adoptedPublishedTimetableStudentCode: {
            get() {
                return TimetableV2.computed.adoptedPublishedTimetableStudentCode.call(context)
            },
        },
        adoptedPublishedTimetableStudentName: {
            get() {
                return TimetableV2.computed.adoptedPublishedTimetableStudentName.call(context)
            },
        },
        adoptedTimetableSaveVisible: {
            get() {
                return TimetableV2.computed.adoptedTimetableSaveVisible.call(context)
            },
        },
        adoptSelectedTimetableV2ButtonLabel: {
            get() {
                return TimetableV2.computed.adoptSelectedTimetableV2ButtonLabel.call(context)
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
        timetableCalculationResultStatusLabel: {
            get() {
                return TimetableV2.computed.timetableCalculationResultStatusLabel.call(context)
            },
        },
        timetableCalculationResultAlertType: {
            get() {
                return TimetableV2.computed.timetableCalculationResultAlertType.call(context)
            },
        },
        timetableCalculationResultAlertIcon: {
            get() {
                return TimetableV2.computed.timetableCalculationResultAlertIcon.call(context)
            },
        },
        timetableCalculationLoadingLabel: {
            get() {
                return TimetableV2.computed.timetableCalculationLoadingLabel.call(context)
            },
        },
        timetableCalculationLoadingIcon: {
            get() {
                return TimetableV2.computed.timetableCalculationLoadingIcon.call(context)
            },
        },
        timetableCalculationProgressVisible: {
            get() {
                return TimetableV2.computed.timetableCalculationProgressVisible.call(context)
            },
        },
        timetableCalculationProgressValue: {
            get() {
                return TimetableV2.computed.timetableCalculationProgressValue.call(context)
            },
        },
        timetableCalculationProgressLabel: {
            get() {
                return TimetableV2.computed.timetableCalculationProgressLabel.call(context)
            },
        },
        timetableCalculationProgressFinalizing: {
            get() {
                return TimetableV2.computed.timetableCalculationProgressFinalizing.call(context)
            },
        },
        selectedCourseItemsClickable: {
            get() {
                return TimetableV2.computed.selectedCourseItemsClickable.call(context)
            },
        },
        selectedCourseItemsDisabled: {
            get() {
                return TimetableV2.computed.selectedCourseItemsDisabled.call(context)
            },
        },
        selectedTimetableSummaryChipsDisabled: {
            get() {
                return TimetableV2.computed.selectedTimetableSummaryChipsDisabled.call(context)
            },
        },
        noSaturdayTimetableCount: {
            get() {
                return TimetableV2.computed.noSaturdayTimetableCount.call(context)
            },
        },
        saturdayFreeQualityCounter: {
            get() {
                return TimetableV2.computed.saturdayFreeQualityCounter.call(context)
            },
        },
        noSaturdayTimetableCountFormatted: {
            get() {
                return TimetableV2.computed.noSaturdayTimetableCountFormatted.call(context)
            },
        },
        selectedTimetableV2SaturdayFreeTestLabel: {
            get() {
                return TimetableV2.computed.selectedTimetableV2SaturdayFreeTestLabel.call(context)
            },
        },
        selectedTimetableV2TestCourseAnalysisItems: {
            get() {
                return TimetableV2.computed.selectedTimetableV2TestCourseAnalysisItems.call(context)
            },
        },
        selectedTimetableV2TestsCardScheduleLabel: {
            get() {
                return TimetableV2.computed.selectedTimetableV2TestsCardScheduleLabel.call(context)
            },
        },
        selectedTimetableV2TestsCardCourseScheduleItems: {
            get() {
                return TimetableV2.computed.selectedTimetableV2TestsCardCourseScheduleItems.call(context)
            },
        },
        selectedTimetableV2TestsCardScheduleComparison: {
            get() {
                return TimetableV2.computed.selectedTimetableV2TestsCardScheduleComparison.call(context)
            },
        },
        selectedTimetableV2TestsCardMatchingCourseScheduleItems: {
            get() {
                return TimetableV2.computed.selectedTimetableV2TestsCardMatchingCourseScheduleItems.call(context)
            },
        },
        selectedTimetableV2TestsCardMatchingScheduleSlots: {
            get() {
                return TimetableV2.computed.selectedTimetableV2TestsCardMatchingScheduleSlots.call(context)
            },
        },
        selectedTimetableV2TestsCardScheduleSlots: {
            get() {
                return TimetableV2.computed.selectedTimetableV2TestsCardScheduleSlots.call(context)
            },
        },
        maxFreeDaysQualityCounter: {
            get() {
                return TimetableV2.computed.maxFreeDaysQualityCounter.call(context)
            },
        },
        noDistanceLearningQualityCounter: {
            get() {
                return TimetableV2.computed.noDistanceLearningQualityCounter.call(context)
            },
        },
        startsFromPeriod10QualityCounter: {
            get() {
                return TimetableV2.computed.startsFromPeriod10QualityCounter.call(context)
            },
        },
        maxFreeDaysTimetableCount: {
            get() {
                return TimetableV2.computed.maxFreeDaysTimetableCount.call(context)
            },
        },
        maxFreeDaysTimetableCountFormatted: {
            get() {
                return TimetableV2.computed.maxFreeDaysTimetableCountFormatted.call(context)
            },
        },
        maxFreeDaysOptionMaximumLabel: {
            get() {
                return TimetableV2.computed.maxFreeDaysOptionMaximumLabel.call(context)
            },
        },
        maxFreeDaysSelectedOptionLabel: {
            get() {
                return TimetableV2.computed.maxFreeDaysSelectedOptionLabel.call(context)
            },
        },
        noDistanceLearningTimetableCount: {
            get() {
                return TimetableV2.computed.noDistanceLearningTimetableCount.call(context)
            },
        },
        noDistanceLearningTimetableCountFormatted: {
            get() {
                return TimetableV2.computed.noDistanceLearningTimetableCountFormatted.call(context)
            },
        },
        startsFromPeriod10TimetableCount: {
            get() {
                return TimetableV2.computed.startsFromPeriod10TimetableCount.call(context)
            },
        },
        startsFromPeriod10TimetableCountFormatted: {
            get() {
                return TimetableV2.computed.startsFromPeriod10TimetableCountFormatted.call(context)
            },
        },
        timetableOptionsChanged: {
            get() {
                return TimetableV2.computed.timetableOptionsChanged.call(context)
            },
        },
        timetableOptionsUnavailable: {
            get() {
                return TimetableV2.computed.timetableOptionsUnavailable.call(context)
            },
        },
        timetableOptionsButtonUnavailable: {
            get() {
                return TimetableV2.computed.timetableOptionsButtonUnavailable.call(context)
            },
        },
        timetableCalculationDisplayTotalCount: {
            get() {
                return TimetableV2.computed.timetableCalculationDisplayTotalCount.call(context)
            },
        },
        timetableCalculationDisplayValidResultCount: {
            get() {
                return TimetableV2.computed.timetableCalculationDisplayValidResultCount.call(context)
            },
        },
        timetableCalculationDisplayConflictResultCount: {
            get() {
                return TimetableV2.computed.timetableCalculationDisplayConflictResultCount.call(context)
            },
        },
        timetableQualityCriteriaRequired: {
            get() {
                return TimetableV2.computed.timetableQualityCriteriaRequired.call(context)
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
        selectedTimetableOptionsSummaryCardVisible: {
            get() {
                return TimetableV2.computed.selectedTimetableOptionsSummaryCardVisible.call(context)
            },
        },
        selectedCourseItems: {
            get() {
                if (selectedCourseItemsOverride !== undefined) return selectedCourseItemsOverride

                return TimetableV2.computed.selectedCourseItems.call(context)
            },
        },
        selectedCourseSummary: {
            get() {
                return TimetableV2.computed.selectedCourseSummary.call(context)
            },
        },
        selectedCourseLimitSummary: {
            get() {
                return TimetableV2.computed.selectedCourseLimitSummary.call(context)
            },
        },
        selectedCourseLimitReached: {
            get() {
                return TimetableV2.computed.selectedCourseLimitReached.call(context)
            },
        },
        selectedCourseLimitExceeded: {
            get() {
                return TimetableV2.computed.selectedCourseLimitExceeded.call(context)
            },
        },
        courseLimitPreselectionResetAvailable: {
            get() {
                return TimetableV2.computed.courseLimitPreselectionResetAvailable.call(context)
            },
        },
        courseLimitPreselectionSignature: {
            get() {
                return TimetableV2.computed.courseLimitPreselectionSignature.call(context)
            },
        },
        selectedAdditionalCourseItems: {
            get() {
                return TimetableV2.computed.selectedAdditionalCourseItems.call(context)
            },
        },
        selectedCompletedCourseItems: {
            get() {
                return TimetableV2.computed.selectedCompletedCourseItems.call(context)
            },
        },
        selectedMissingCourseCardItems: {
            get() {
                return TimetableV2.computed.selectedMissingCourseCardItems.call(context)
            },
        },
        selectedSemesterCourseItems: {
            get() {
                return TimetableV2.computed.selectedSemesterCourseItems.call(context)
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
        selectedTimetableOptionItemsDeletable: {
            get() {
                return TimetableV2.computed.selectedTimetableOptionItemsDeletable.call(context)
            },
        },
        selectedReviewCourseItem: {
            get() {
                return TimetableV2.computed.selectedReviewCourseItem.call(context)
            },
        },
        selectedCourseOfferCardVisible: {
            get() {
                return TimetableV2.computed.selectedCourseOfferCardVisible.call(context)
            },
        },
        selectedCourseOfferItemsSelectable: {
            get() {
                return TimetableV2.computed.selectedCourseOfferItemsSelectable.call(context)
            },
        },
        storedTimetableV2Selection: {
            get() {
                return TimetableV2.computed.storedTimetableV2Selection.call(context)
            },
        },
        effectiveTimetableV2Selection: {
            get() {
                return TimetableV2.computed.effectiveTimetableV2Selection.call(context)
            },
        },
        noStudentPlannedCourseItems: {
            get() {
                return TimetableV2.computed.noStudentPlannedCourseItems.call(context)
            },
        },
        storedTimetableSelectionSummary: {
            get() {
                return TimetableV2.computed.storedTimetableSelectionSummary.call(context)
            },
        },
        storedTimetableV2Options: {
            get() {
                return TimetableV2.computed.storedTimetableV2Options.call(context)
            },
        },
        storedCourseSelectionOverrides: {
            get() {
                return TimetableV2.computed.storedCourseSelectionOverrides.call(context)
            },
        },
        storedCompletedCourseItems: {
            get() {
                if (storedCompletedCourseItemsOverride !== undefined) return storedCompletedCourseItemsOverride

                return TimetableV2.computed.storedCompletedCourseItems.call(context)
            },
        },
        storedMissingCourseItems: {
            configurable: true,
            get() {
                return TimetableV2.computed.storedMissingCourseItems.call(context)
            },
        },
        storedMissingCourseCardItems: {
            configurable: true,
            get() {
                if (storedMissingCourseCardItemsOverride !== undefined) return storedMissingCourseCardItemsOverride

                return TimetableV2.computed.storedMissingCourseCardItems.call(context)
            },
        },
        storedSemesterCourseItems: {
            configurable: true,
            get() {
                if (storedSemesterCourseItemsOverride !== undefined) return storedSemesterCourseItemsOverride

                return TimetableV2.computed.storedSemesterCourseItems.call(context)
            },
        },
        storedPlannedCourseItems: {
            configurable: true,
            get() {
                if (storedPlannedCourseItemsOverride !== undefined) return storedPlannedCourseItemsOverride

                return TimetableV2.computed.storedPlannedCourseItems.call(context)
            },
        },
        visitedCourseItems: {
            get() {
                return TimetableV2.computed.visitedCourseItems.call(context)
            },
        },
        courseSelectionOverrides: {
            get() {
                if (courseSelectionOverridesOverride !== undefined) return courseSelectionOverridesOverride

                return TimetableV2.computed.courseSelectionOverrides.call(context)
            },
        },
        courseSelectionDraftChanged: {
            get() {
                return TimetableV2.computed.courseSelectionDraftChanged.call(context)
            },
        },
        storedTimetableStudentCode: {
            get() {
                return TimetableV2.computed.storedTimetableStudentCode.call(context)
            },
        },
        storedTimetableStudentEmail: {
            get() {
                return TimetableV2.computed.storedTimetableStudentEmail.call(context)
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
        includeDistanceLearningCourseVariants: {
            get() {
                return TimetableV2.computed.includeDistanceLearningCourseVariants.call(context)
            },
        },
        includeKompaktunterrichtCourseVariants: {
            get() {
                return TimetableV2.computed.includeKompaktunterrichtCourseVariants.call(context)
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
        selectedTimetableV2RestartButtonInHeaderVisible: {
            get() {
                return TimetableV2.computed.selectedTimetableV2RestartButtonInHeaderVisible.call(context)
            },
        },
        selectedTimetableV2ConflictSeverity: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ConflictSeverity.call(context)
            },
        },
        selectedTimetableV2HasErrorConflicts: {
            get() {
                return TimetableV2.computed.selectedTimetableV2HasErrorConflicts.call(context)
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
        selectedTimetableV2ApparentOverlapSummaryItems: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ApparentOverlapSummaryItems.call(context)
            },
        },
        selectedTimetableV2ConflictResolutionOptions: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ConflictResolutionOptions.call(context)
            },
        },
        selectedTimetableV2ProblemCourseItems: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ProblemCourseItems.call(context)
            },
        },
        selectedTimetableV2ProblemCourseActionsVisible: {
            get() {
                return TimetableV2.computed.selectedTimetableV2ProblemCourseActionsVisible.call(context)
            },
        },
        selectedTimetableV2NoResultResolutionCourseItems: {
            get() {
                return TimetableV2.computed.selectedTimetableV2NoResultResolutionCourseItems.call(context)
            },
        },
        selectedTimetableV2NoResultResolutionOptionItems: {
            get() {
                return TimetableV2.computed.selectedTimetableV2NoResultResolutionOptionItems.call(context)
            },
        },
        selectedTimetableV2NoResultResolutionActionsVisible: {
            get() {
                return TimetableV2.computed.selectedTimetableV2NoResultResolutionActionsVisible.call(context)
            },
        },
        moreCoursesCardItems: {
            get() {
                return TimetableV2.computed.moreCoursesCardItems.call(context)
            },
        },
        moreCoursesCategoryCards: {
            get() {
                return TimetableV2.computed.moreCoursesCategoryCards.call(context)
            },
        },
        moreAdoptedCourseCards: {
            get() {
                return TimetableV2.computed.moreAdoptedCourseCards.call(context)
            },
        },
        moreAdoptedCoursesTitle: {
            get() {
                return TimetableV2.computed.moreAdoptedCoursesTitle.call(context)
            },
        },
        activeMoreAdoptedCourseCard: {
            get() {
                return TimetableV2.computed.activeMoreAdoptedCourseCard.call(context)
            },
        },
        moreAdoptedCourseCandidateItems: {
            get() {
                return TimetableV2.computed.moreAdoptedCourseCandidateItems.call(context)
            },
        },
        moreAdoptedCourseItems: {
            get() {
                return TimetableV2.computed.moreAdoptedCourseItems.call(context)
            },
        },
        moreAdoptedCourseTopLevelItems: {
            get() {
                return TimetableV2.computed.moreAdoptedCourseTopLevelItems.call(context)
            },
        },
        selectedMoreExistingCourseBaseItem: {
            get() {
                return TimetableV2.computed.selectedMoreExistingCourseBaseItem.call(context)
            },
        },
        selectedMoreAdoptedCourseItem: {
            get() {
                return TimetableV2.computed.selectedMoreAdoptedCourseItem.call(context)
            },
        },
        selectedMoreAdoptedCourseOfferedCourseItems: {
            get() {
                return TimetableV2.computed.selectedMoreAdoptedCourseOfferedCourseItems.call(context)
            },
        },
        moreCourseAvailabilityCandidateItems: {
            get() {
                return TimetableV2.computed.moreCourseAvailabilityCandidateItems.call(context)
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
        hasPendingRemovedSelectedCourses: {
            get() {
                return TimetableV2.computed.hasPendingRemovedSelectedCourses.call(context)
            },
        },
        timetablePendingActionConfirmationVisible: {
            get() {
                return TimetableV2.computed.timetablePendingActionConfirmationVisible.call(context)
            },
        },
        adoptedTimetableCourseRemovalPendingVisible: {
            get() {
                return TimetableV2.computed.adoptedTimetableCourseRemovalPendingVisible.call(context)
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
        selectedTimetableV2CourseCodes: {
            get() {
                return TimetableV2.computed.selectedTimetableV2CourseCodes.call(context)
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
    it('maps compact aggregate course codes to their concrete aliases', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
        }

        expect(methods.courseCodeAliases.call(ctx, { code: 'MEMU2' })).toEqual(['ME2'])
        expect(methods.courseCodeAliases.call(ctx, { code: 'Ris4' })).toContain('R4')
        expect(methods.courseCodeAliases.call(ctx, { code: 'R4' })).toContain('RIS4')
    })

    it('formats timetable course codes with canonical short names for screen and print labels', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
        }

        expect([
            'Rev',
            'Rk',
            'Ris',
            'Ror',
            'D',
            'E',
            'F',
            'L',
            'SPA',
            'GWB',
            'GPB',
            'BU',
            'CH',
            'PH',
            'PP',
            'MU',
            'KG',
            'INF',
            'ÖKO',
        ].map((courseCode) => methods.courseDisplayLabel.call(ctx, courseCode))).toEqual([
            'Rev',
            'Rk',
            'Ris',
            'Ror',
            'D',
            'E',
            'F',
            'L',
            'SPA',
            'GWB',
            'GPB',
            'BU',
            'CH',
            'PH',
            'PP',
            'MU',
            'KG',
            'INF',
            'ÖKO',
        ])
        expect(methods.courseDisplayLabel.call(ctx, 'INF2-Grp1-FU')).toBe('INF2-Grp1-FU')
        expect(methods.courseDisplayLabel.call(ctx, 'GW1')).toBe('GWB1')
        expect(methods.courseDisplayLabel.call(ctx, 'OEK1')).toBe('ÖKO1')
        expect(methods.courseDisplayLabel.call(ctx, 'S1')).toBe('SPA1')
    })

    it('shows a copyable student email in the selected student card, review header, and student search results', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')

        expect(source).toContain('class="students-timetable-v2-review-card__student-email"')
        expect(source).toContain('@click.stop="copyCourseReviewStudentEmail"')
        expect(source).toContain('class="ttv2-strip__student-email"')
        expect(source).toContain('@click.stop="copyStoredTimetableStudentEmail"')
        expect(source).toContain('class="ttv2-strip__student-icon"')
        expect(source).toContain('class="ttv2-strip__student-meta"')
        expect(source).toMatch(/\.ttv2-strip\s*\{[\s\S]*width: 100%;[\s\S]*background: rgba\(255, 255, 255, 0\.94\);/)
        expect(source).toMatch(/\.ttv2-strip__student\s*\{\s*display: flex;[\s\S]*align-items: center;/)
        expect(source).toMatch(/\.ttv2-strip__student-email\s*\{\s*display: inline-flex;/)
        expect(source).toMatch(/@media \(max-width: 640px\) \{[\s\S]*\.ttv2-strip\s*\{[\s\S]*flex-direction: column;/)
        expect(source).toMatch(/\.students-timetable-v2-student-search-results\s*\{\s*display: grid;\s*gap: 10px;/)
        expect(source).toMatch(/\.students-timetable-v2-student-search-results__item-content\s*\{\s*display: grid;[\s\S]*gap: 3px;[\s\S]*grid-template-columns: minmax\(0, 1fr\);[\s\S]*padding-bottom: 2px;/)
        expect(source).toMatch(/\.students-timetable-v2-student-search-results__name\s*\{[\s\S]*color: rgb\(var\(--v-theme-primary\)\);/)
        expect(source).toContain('class="students-timetable-v2-student-search-results__email"')
        expect(source).toMatch(/\.students-timetable-v2-student-search-results__email\s*\{\s*font-weight: 400;\s*justify-self: start;/)
        expect(source).toContain('@click.stop="copyStudentEmail(student)"')
        expect(source).toContain("copiedStudentEmailCode === normalizedStudentCode(student.student_code) ? 'mdi-check' : 'mdi-content-copy'")
        expect(source).toContain('E-Mail-Adresse kopieren')
        expect(source).toContain('Kopiert')
    })

    it('copies a student email and clears the copied state after feedback', async () => {
        vi.useFakeTimers()

        try {
            const methods = TimetableV2.methods
            const context = {
                copiedStudentEmailCode: null,
                copyStudentEmailResetTimeout: null,
                studentEmail: methods.studentEmail,
                normalizedStudentCode: methods.normalizedStudentCode,
                copyTextToClipboard: vi.fn().mockResolvedValue(true),
                clearCopyStudentEmailResetTimeout: methods.clearCopyStudentEmailResetTimeout,
            }

            await expect(methods.copyStudentEmail.call(context, {
                student_code: ' 100 ',
                email: ' isabella.zadra@example.test ',
            })).resolves.toBe(true)

            expect(context.copyTextToClipboard).toHaveBeenCalledWith('isabella.zadra@example.test')
            expect(context.copiedStudentEmailCode).toBe('100')

            vi.advanceTimersByTime(1800)

            expect(context.copiedStudentEmailCode).toBeNull()
            expect(context.copyStudentEmailResetTimeout).toBeNull()
        } finally {
            vi.useRealTimers()
        }
    })

    it('copies the stored student email through the shared student email copy flow', async () => {
        const methods = TimetableV2.methods
        const context = {
            storedTimetableStudentCode: '100',
            storedTimetableStudentEmail: 'isabella.zadra@example.test',
            copyStudentEmail: vi.fn().mockResolvedValue(true),
        }

        await expect(methods.copyStoredTimetableStudentEmail.call(context)).resolves.toBe(true)

        expect(context.copyStudentEmail).toHaveBeenCalledWith({
            student_code: '100',
            email: 'isabella.zadra@example.test',
        })
    })

    it('copies the review student email through the shared student email copy flow', async () => {
        const methods = TimetableV2.methods
        const context = {
            courseReviewStudentCode: '100',
            courseReviewStudentEmail: 'isabella.zadra@example.test',
            copyStudentEmail: vi.fn().mockResolvedValue(true),
        }

        await expect(methods.copyCourseReviewStudentEmail.call(context)).resolves.toBe(true)

        expect(context.copyStudentEmail).toHaveBeenCalledWith({
            student_code: '100',
            email: 'isabella.zadra@example.test',
        })
    })

    it('resolves the review student email for course review and adoption steps', () => {
        const reviewContext = timetableV2Context({
            timetableV2Step: 'course-review',
            storedTimetableStudentContext: {
                student: {
                    studentCode: '100',
                    label: '5K · SOLLEDER Luis · Semester 5',
                    email: 'luis.solleder@example.test',
                },
            },
        })
        const adoptedContext = timetableV2Context({
            timetableV2Step: 'timetable-adoption',
            robotStudents: [
                { student_code: '200', email: 'adopted.student@example.test' },
            ],
            adoptedTimetableStudentContextSnapshot: {
                student: {
                    studentCode: '200',
                    label: '5K · SOLLEDER Luis · Semester 5',
                },
            },
        })

        expect(TimetableV2.computed.courseReviewStudentEmail.call(reviewContext)).toBe('luis.solleder@example.test')
        expect(TimetableV2.computed.courseReviewStudentEmail.call(adoptedContext)).toBe('adopted.student@example.test')
    })

    it('shows calculation action buttons and disables them while timetables are loading', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const courseSelectionCardsSource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/CourseSelectionCards.vue', 'utf8')

        expect(source).toMatch(/v-if="timetableCalculationVisible && timetableOptionsCardVisible" class="students-timetable-v2-calculation-card__actions"[\s\S]*@click="closeTimetableOptionsCard">\s+Abbruch/u)
        expect(source).toMatch(/class="students-timetable-v2-calculation-card__heading"[\s\S]*\{\{ timetableCalculationCardTitleLabel \}\}[\s\S]*v-if="timetableCalculationProgressVisible"[\s\S]*class="students-timetable-v2-calculation-card__title-progress"[\s\S]*:model-value="timetableCalculationProgressValue"[\s\S]*max="100"[\s\S]*\{\{ timetableCalculationProgressLabel \}\}/u)
        expect(source).not.toContain('v-progress-circular')
        expect(source).not.toContain('students-timetable-v2-calculation-card__availability-loader')
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__heading \{[\s\S]*display: inline-flex;[\s\S]*align-items: center;/u)
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__title-progress \{[\s\S]*width: clamp\(140px, 24vw, 220px\);/u)
        expect(source).toMatch(/v-if="\(timetableV2PageLoading \|\| timetableCalculationLoading\) && !timetableCalculationProgressVisible"[\s\S]*indeterminate/u)
        expect(source).toMatch(/color="success"\s+variant="flat"\s+append-icon="mdi-check"\s+:disabled="!timetableOptionsChanged \|\| timetableCalculationLoading"\s+@click="applyTimetableOptions">\s+Anwenden/u)
        expect(source).toMatch(/<v-text-field[\s\S]*:model-value="timetableCalculationNumberDraft"[\s\S]*type="number"[\s\S]*density="comfortable"[\s\S]*hide-spin-buttons[\s\S]*prefix="Nr\."[\s\S]*@keydown\.enter\.prevent="commitSelectedTimetableV2Number"[\s\S]*@blur="commitSelectedTimetableV2Number"/u)
        expect(source).toMatch(/\.students-timetable-v2-result__number-input \{[\s\S]*width: 112px;/u)
        expect(source).toMatch(/\.students-timetable-v2-result__number-input :deep\(\.v-field\) \{[\s\S]*min-height: 40px;/u)
        expect(source).toMatch(/\.students-timetable-v2-result__number-input :deep\(\.v-field__field\) \{[\s\S]*align-items: center;[\s\S]*min-height: 40px;/u)
        expect(source).toMatch(/\.students-timetable-v2-result__number-input :deep\(\.v-text-field__prefix\) \{[\s\S]*align-items: center;[\s\S]*padding-top: 0;[\s\S]*padding-bottom: 0;/u)
        expect(source).toMatch(/class="students-timetable-v2-calculation-card__success"[\s\S]*\{\{ timetableCalculationResultStatusLabel \}\}[\s\S]*class="students-timetable-v2-calculation-card__summary"[\s\S]*class="students-timetable-v2-calculation-card__timetable-selector students-timetable-v2-result__meta"[\s\S]*aria-label="Vorheriger Stundenplan"[\s\S]*<v-text-field[\s\S]*aria-label="Stundenplan Nummer"/u)
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__timetable-selector \{[\s\S]*justify-content: flex-end;[\s\S]*margin-left: auto;/u)
        expect(source).toMatch(/@media \(max-width: 640px\) \{[\s\S]*\.students-timetable-v2-calculation-card__content \{[\s\S]*padding: 10px !important;[\s\S]*\.students-timetable-v2-result-grid \{[\s\S]*grid-template-columns: minmax\(34px, 0\.58fr\) repeat\(var\(--students-timetable-v2-result-weekdays, 5\), minmax\(0, 1fr\)\);[\s\S]*overflow-x: visible;[\s\S]*\.students-timetable-v2-result-grid__details,[\s\S]*\.students-timetable-v2-result-grid__conflicts \{[\s\S]*display: none;/u)
        expect(source).toContain(':disabled="!selectedTimetableV2PreviousAvailable || timetablePendingActionConfirmationVisible"')
        expect(source).toContain(':disabled="!selectedTimetableV2NextAvailable || timetablePendingActionConfirmationVisible"')
        expect(source).toContain(':disabled="timetableCalculationLoading || timetablePendingActionConfirmationVisible"')
        expect(source).not.toContain('v-if="timetableCalculationVisible" class="students-timetable-v2-result__meta"')
        expect(source).toContain(":class=\"{ 'students-timetable-v2-calculation-card--final': adoptedTimetableVisible }\"")
        expect(source).toMatch(/\.students-timetable-v2-calculation-card--final \{[\s\S]*border-color: rgba\(22, 163, 74, 0\.28\);[\s\S]*background: rgba\(240, 253, 244, 0\.96\);/u)
        expect(source).toMatch(/class="students-timetable-v2-tests-card"[\s\S]*Tests[\s\S]*v-for="course in selectedTimetableV2TestsCardCourseScheduleItems"[\s\S]*\{\{ course\.displayLabel \}\} \(\{\{ course\.typeLabel \}\}\):[\s\S]*\{\{ course\.scheduleLabel \}\}[\s\S]*Vergleich:[\s\S]*selectedTimetableV2TestsCardScheduleComparison\.color[\s\S]*\{\{ selectedTimetableV2TestsCardScheduleComparison\.label \}\}/u)
        expect(source).toContain("{ displayLabel: '1. D - 5 - 3R - SHAM', key: 'D5-3R-SHAM', label: 'D5-3R-SHAM' }")
        expect(source).toContain("{ displayLabel: '2. E - 5 - 3R - HÖF', key: 'E5-3R-HOEF', label: 'E5-3R-HÖF' }")
        expect(source).toContain("{ displayLabel: '3. M - 4 - 3R - SCHM', key: 'M4-3R-SCHM', label: 'M4-3R-SCHM' }")
        expect(source).toContain("{ displayLabel: '4. M - 5 - 3R - SCHM', key: 'M5-3R-SCHM', label: 'M5-3R-SCHM' }")
        expect(source).toContain("{ displayLabel: '5. E - 6 - 3R - HÖF', key: 'E6-3R-HOEF', label: 'E6-3R-HÖF' }")
        expect(source).toContain("label: 'Gleiche Tage/Uhrzeiten/Daten: Nein'")
        expect(source).toContain("label: `Gleiche Tage/Uhrzeiten/Daten: Ja - ${matchingLabels.join('; ')}`")
        expect(source).not.toContain('{{ selectedTimetableV2SaturdayFreeTestLabel }}')
        expect(source).not.toContain('v-for="item in selectedTimetableV2TestCourseAnalysisItems"')
        expect(source).toMatch(/\.students-timetable-v2-tests-card \{[\s\S]*border: 1px solid rgba\(14, 165, 233, 0\.18\);/u)
        expect(source).toMatch(/\.students-timetable-v2-tests-card__info :deep\(\.v-chip__content\) \{[\s\S]*display: flex;[\s\S]*flex-wrap: wrap;[\s\S]*white-space: normal;/u)
        expect(source).toMatch(/class="students-timetable-v2-result__title"[\s\S]*v-if="selectedTimetableV2RestartButtonInHeaderVisible"[\s\S]*prepend-icon="mdi-restart"[\s\S]*@click="restartTimetableV2">\s+Neustart/u)
        expect(source).toMatch(/v-if="selectedTimetableV2RestartButtonInHeaderVisible \|\| adoptedTimetableVisible"[\s\S]*class="students-timetable-v2-result__meta"[\s\S]*v-if="selectedTimetableV2RestartButtonInHeaderVisible"[\s\S]*prepend-icon="mdi-arrow-left"[\s\S]*@click="adoptedTimetableVisible \? backToTimetableCalculation\(\) : backToCourseReview\(\)">\s+Zurück/u)
        expect(source).toContain('v-if="(calculationButtonCardVisible || adoptedTimetableButtonCardVisible) && !selectedTimetableV2Result"')
        expect(source).not.toContain('Gültiger Stundenplan')
        expect(source).toMatch(/class="students-timetable-v2-result-grid"[\s\S]*class="students-timetable-v2-result-conflicts"[\s\S]*\{\{ selectedTimetableV2ConflictTitle \}\}/u)
        expect(source).toContain('class="students-timetable-v2-result-grid__distance-learning"')
        expect(source).toContain('Fernunterricht')
        expect(source).toMatch(/\.students-timetable-v2-result-grid__conflict \{[\s\S]*font-size: 0\.82rem;[\s\S]*font-weight: 900;/u)
        expect(source).not.toContain('>\n                                                    FU\n                                                </sup>')
        expect(source).toContain('v-if="selectedTimetableV2ConflictResolutionActionsVisible"')
        expect(source).toMatch(/:color="option\.color"\s+variant="tonal"\s+:prepend-icon="option\.icon"\s+:class="\{ 'students-timetable-v2-result-conflicts__action--recommended': option\.recommended \}"\s+:disabled="timetablePendingActionConfirmationVisible"\s+@click="applySelectedTimetableV2ConflictResolution\(option\)"/u)
        expect(source).toMatch(/v-else-if="timetableCalculationVisible && !moreCoursesCardVisible" class="students-timetable-v2-calculation-card__actions"/u)
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__actions \{[\s\S]*justify-content: flex-end;[\s\S]*flex: 0 1 auto;[\s\S]*margin-left: auto;/u)
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__reset-button \{[\s\S]*margin-left: 0;/u)
        expect(source).toMatch(/v-else-if="timetableCalculationVisible && !moreCoursesCardVisible"[\s\S]*class="students-timetable-v2-calculation-card__primary-actions"[\s\S]*:color="moreCoursesButtonUnavailable \? 'error' : 'primary'"\s+variant="tonal"\s+prepend-icon="mdi-plus-circle-outline"\s+:disabled="timetableCalculationLoading \|\| moreCourseAvailabilityLoading"\s+@click="toggleMoreCoursesCard">\s+Mehr Module/u)
        expect(source).toMatch(/:color="timetableOptionsButtonUnavailable \? 'error' : 'info'"\s+variant="tonal"\s+prepend-icon="mdi-cog-outline"\s+:disabled="timetableCalculationLoading \|\| timetableQualityCountersLoading \|\| moreCourseAvailabilityLoading \|\| timetableOptionsButtonUnavailable"\s+:aria-expanded="timetableOptionsCardVisible \? 'true' : 'false'"\s+@click="toggleTimetableOptionsCard">\s+Optionen/u)
        expect(source).toMatch(/v-if="timetableCalculationResetAvailable"\s+size="large"\s+color="warning"\s+variant="tonal"\s+prepend-icon="mdi-restore"\s+class="students-timetable-v2-calculation-card__reset-button"\s+:disabled="timetableCalculationLoading"\s+@click="resetTimetableCalculationChanges">\s+Zurücksetzen/u)
        expect(source).toMatch(/v-else-if="timetableCalculationVisible && moreCoursesVisible && !moreCoursesCardVisible"[\s\S]*@click="cancelMoreCoursesCard">\s+Abbruch[\s\S]*color="error"\s+variant="flat"\s+prepend-icon="mdi-delete"\s+:loading="timetableCalculationLoading"\s+:disabled="!moreCoursesSelectionChanged \|\| timetableCalculationLoading"\s+@click="applyMoreCoursesSelection">\s+Löschen/u)
        const pendingCourseRemovalActionsSource = source.slice(
            source.indexOf('v-else-if="timetableCalculationVisible && moreCoursesVisible && !moreCoursesCardVisible"'),
            source.indexOf('v-else-if="timetableCalculationVisible && !moreCoursesCardVisible"'),
        )

        expect(pendingCourseRemovalActionsSource).not.toContain('Mehr Module')
        expect(pendingCourseRemovalActionsSource).not.toContain('Optionen')
        expect(pendingCourseRemovalActionsSource).not.toContain('Zurücksetzen')
        const moreCoursesHeaderActionsSource = source.slice(
            source.indexOf('v-else-if="timetableCalculationVisible && !moreCoursesCardVisible" class="students-timetable-v2-calculation-card__actions"'),
            source.indexOf('<span v-else-if="timetableCalculationVisible" class="students-timetable-v2-calculation-card__more-course-actions"'),
        )

        expect(moreCoursesHeaderActionsSource).not.toContain('@click="applyMoreCoursesSelection"')
        expect(source).toMatch(/prepend-icon="mdi-tune-variant"\s+:disabled="!courseLimitPreselectionResetAvailable"\s+@click="applyCourseLimitPreselection\(true\)">\s+Vorauswahl zurücksetzen/u)
        expect(source).toContain(':icon="timetableCalculationLoadingIcon"')
        expect(source).toContain('{{ timetableCalculationLoadingLabel }}')
        expect(source).toContain('students-timetable-v2-card-column students-timetable-v2-selected-courses-column')
        expect(source).toContain('/api/admin/students-timetables/timetable-v2-selection-bootstrap')
        expect(source).toContain("axios.put('/api/admin/students-timetables/timetable-v2-state', { state })")
        expect(source).not.toContain('localStorage')
        expect(source).toMatch(/Ausgewählte Module[\s\S]*v-if="adoptedTimetableVisible && !adoptedTimetableCourseRemovalPendingVisible"[\s\S]*class="students-timetable-v2-card students-timetable-v2-more-adopted-courses-card"[\s\S]*Weitere Module/u)
        expect(source).toContain('{{ moreAdoptedCoursesTitle }}')
        expect(source).toContain('class="students-timetable-v2-more-adopted-courses-card__content"')
        expect(source).toContain('v-if="!activeMoreAdoptedCourseCard"')
        expect(source).toContain('v-for="card in moreAdoptedCourseCards"')
        expect(source).toContain('{{ card.title }}')
        expect(source).toContain(':disabled="moreAdoptedCourseCardDisabled(card)"')
        expect(source).toContain("'students-timetable-v2-more-adopted-courses-card__category--disabled': moreAdoptedCourseCardDisabled(card)")
        expect(source).toContain('@click="openMoreAdoptedCourseCard(card)"')
        expect(source).toContain('class="students-timetable-v2-more-adopted-courses-card__back-card"')
        expect(source).toContain('@click="closeMoreAdoptedCourseCard"')
        expect(source).toContain('v-for="course in moreAdoptedCourseItems"')
        expect(source).toMatch(/<v-card[\s\S]*v-for="course in moreAdoptedCourseItems"[\s\S]*class="students-timetable-v2-more-adopted-courses-card__course"/u)
        expect(source).toContain('class="students-timetable-v2-more-adopted-courses-card__course-label"')
        expect(source).toContain('@click="openMoreAdoptedCourseItem(course)"')
        expect(source).toContain('v-if="selectedMoreAdoptedCourseItem"')
        expect(source).toContain('v-for="course in selectedMoreAdoptedCourseOfferedCourseItems"')
        expect(source).toContain('@click="insertMoreAdoptedCourseOfferIntoTimetable(course)"')
        expect(source).toMatch(/\.students-timetable-v2-more-adopted-courses-card__grid \{[\s\S]*grid-template-columns: repeat\(5, minmax\(0, 1fr\)\);/u)
        expect(source).toContain('.students-timetable-v2-more-adopted-courses-card__category--disabled')
        expect(source).toMatch(/\.students-timetable-v2-more-adopted-courses-card__category-title \{[\s\S]*overflow-wrap: anywhere;[\s\S]*hyphens: auto;/u)
        expect(source).toMatch(/@media \(max-width: 640px\) \{[\s\S]*\.students-timetable-v2-more-adopted-courses-card__grid \{[\s\S]*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);[\s\S]*\.students-timetable-v2-more-adopted-courses-card__category \{[\s\S]*min-height: 64px;[\s\S]*\.students-timetable-v2-more-adopted-courses-card__category-title \{[\s\S]*font-size: 0\.82rem;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-adopted-courses-card__course-list \{[\s\S]*grid-template-columns: repeat\(auto-fit, minmax\(116px, 1fr\)\);/u)
        expect(source).toMatch(/\.students-timetable-v2-more-adopted-courses-card__course \{[\s\S]*min-height: 64px;/u)
        expect(source).toContain('v-for="card in moreCoursesCategoryCards"')
        expect(source).toContain('v-for="course in activeMoreCoursesCategoryCard.items"')
        expect(source).toContain("title: 'Abgeschlossene'")
        expect(source).toContain("title: 'Negative'")
        expect(source).toContain("title: 'Frühere'")
        expect(source).toContain("title: 'Aktuelle'")
        expect(source).toContain("title: 'Zusätzliche'")
        expect(source).not.toContain('{{ card.emptyLabel }}')
        expect(source).not.toContain('students-timetable-v2-selected-options-card')
        expect(source).not.toContain('v-if="selectedTimetableOptionsSummaryCardVisible"')
        expect(source).not.toContain('Keine Optionen ausgewählt')
        expect(source).toContain(':closable="selectedCourseItemsDeletable"')
        expect(source).toContain(':disabled="selectedCourseItemsDisabled"')
        expect(source).toContain(':color="selectedCourseItemColor(course)"')
        expect(source).toMatch(/v-for="course in selectedCourseItems"[\s\S]*size="large"[\s\S]*students-timetable-v2-selected-courses-card__course--button/u)
        expect(source).toMatch(/\.students-timetable-v2-selected-courses-card__course--button \{[\s\S]*min-height: 44px !important;[\s\S]*padding-inline: 18px !important;[\s\S]*border-radius: 8px !important;/u)
        expect(source).toContain(":role=\"selectedCourseItemsClickable && !selectedCourseItemsDisabled ? 'button' : undefined\"")
        expect(source).toContain('.students-timetable-v2-selected-courses-card__course--active')
        expect(source).toContain("'students-timetable-v2-selected-courses-card__course--conflict': selectedCourseItemHasConflict(course)")
        expect(source).toContain('.students-timetable-v2-completed-courses__item--selected')
        expect(source).toContain('background: #16a34a !important;')
        expect(source).toContain('border-color: #15803d !important;')
        expect(source).toContain('<v-col v-if="selectedCourseOfferCardVisible" cols="12" class="students-timetable-v2-card-column">')
        expect(source).toContain('<v-card-actions v-if="courseReviewVisible" class="students-timetable-v2-course-card-footer">')
        expect(source).toContain('Hier können einzelne Module (z.B. Fernunterricht) abgewählt werden.')
        expect(source).toMatch(/v-else-if="timetableCalculationVisible && timetableCalculationResult && !moreCoursesVisible && !timetableOptionsCardVisible"/u)
        expect(source).toMatch(/v-if="timetableOptionsCardVisible"[\s\S]*class="students-timetable-v2-options-card"[\s\S]*Optionen/u)
        expect(source).toContain('class="students-timetable-v2-options-card__list"')
        expect(source).toMatch(/\.students-timetable-v2-options-card__list \{[\s\S]*display: flex;[\s\S]*flex-wrap: wrap;/u)
        expect(source).toMatch(/<v-card\s+v-if="!timetableNoSaturdaySelected"[\s\S]*@click="toggleNoSaturdayTimetableOption"/u)
        expect(source).toContain(":color=\"timetableNoSaturdayDraftSelected ? 'success' : undefined\"")
        expect(source).toContain(':ripple="!timetableOptionUnavailable(\'no-saturday\')"')
        expect(source).toContain("'students-timetable-v2-options-card__option--selected': timetableNoSaturdayDraftSelected")
        expect(source).toContain("'students-timetable-v2-options-card__option--unavailable': timetableOptionUnavailable('no-saturday')")
        expect(source).toMatch(/\.students-timetable-v2-options-card__option--unavailable \{[\s\S]*border-color: rgba\(148, 163, 184, 0\.34\);[\s\S]*background: rgba\(241, 245, 249, 0\.96\);/u)
        expect(source).toContain("'students-timetable-v2-options-card__option--loading': timetableCalculationLoading")
        expect(source).toContain(':tabindex="timetableOptionUnavailable(\'no-saturday\') ? -1 : 0"')
        expect(source).toContain(":aria-disabled=\"timetableOptionUnavailable('no-saturday') ? 'true' : 'false'\"")
        expect(source).toContain(":aria-pressed=\"timetableNoSaturdayDraftSelected ? 'true' : 'false'\"")
        expect(source).toContain('@click="toggleNoSaturdayTimetableOption"')
        expect(source).toContain('Kein Samstag')
        expect(source).toContain('{{ noSaturdayTimetableCountFormatted }}')
        expect(source).toMatch(/<v-card\s+v-if="!timetableStartsFromPeriod10Selected"[\s\S]*@click="toggleStartsFromPeriod10TimetableOption"/u)
        expect(source).toContain(":color=\"timetableStartsFromPeriod10DraftSelected ? 'success' : undefined\"")
        expect(source).toContain(':ripple="!timetableOptionUnavailable(\'starts-from-period-10\')"')
        expect(source).toContain("'students-timetable-v2-options-card__option--selected': timetableStartsFromPeriod10DraftSelected")
        expect(source).toContain("'students-timetable-v2-options-card__option--unavailable': timetableOptionUnavailable('starts-from-period-10')")
        expect(source).toContain("'students-timetable-v2-options-card__option--loading': timetableCalculationLoading")
        expect(source).toContain(':tabindex="timetableOptionUnavailable(\'starts-from-period-10\') ? -1 : 0"')
        expect(source).toContain(":aria-disabled=\"timetableOptionUnavailable('starts-from-period-10') ? 'true' : 'false'\"")
        expect(source).toContain(":aria-pressed=\"timetableStartsFromPeriod10DraftSelected ? 'true' : 'false'\"")
        expect(source).toContain('@click="toggleStartsFromPeriod10TimetableOption"')
        expect(source).toContain('Erst ab 10. Stunde')
        expect(source).toContain('{{ startsFromPeriod10TimetableCountFormatted }}')
        expect(source).toMatch(/<v-card\s+v-if="!timetableMaxFreeDaysSelected"[\s\S]*@click="toggleMaxFreeDaysTimetableOption"/u)
        expect(source).toContain(":color=\"timetableMaxFreeDaysDraftSelected ? 'success' : undefined\"")
        expect(source).toContain(':ripple="!timetableOptionUnavailable(\'max-free-days\')"')
        expect(source).toContain("'students-timetable-v2-options-card__option--selected': timetableMaxFreeDaysDraftSelected")
        expect(source).toContain("'students-timetable-v2-options-card__option--unavailable': timetableOptionUnavailable('max-free-days')")
        expect(source).toContain("'students-timetable-v2-options-card__option--loading': timetableCalculationLoading")
        expect(source).toContain(':tabindex="timetableOptionUnavailable(\'max-free-days\') ? -1 : 0"')
        expect(source).toContain(":aria-disabled=\"timetableOptionUnavailable('max-free-days') ? 'true' : 'false'\"")
        expect(source).toContain(":aria-pressed=\"timetableMaxFreeDaysDraftSelected ? 'true' : 'false'\"")
        expect(source).toContain('@click="toggleMaxFreeDaysTimetableOption"')
        expect(source).toMatch(/<v-card-title class="students-timetable-v2-options-card__option-title">\s*\{\{ maxFreeDaysOptionMaximumLabel \}\}\s*<\/v-card-title>/u)
        expect(source).toMatch(/<span class="students-timetable-v2-options-card__option-count">\s*\{\{ maxFreeDaysTimetableCountFormatted \}\}\s*<\/span>/u)
        expect(source).toMatch(/<v-card\s+v-if="!timetableNoDistanceLearningSelected"[\s\S]*@click="toggleNoDistanceLearningTimetableOption"/u)
        expect(source).toContain(":color=\"timetableNoDistanceLearningDraftSelected ? 'success' : undefined\"")
        expect(source).toContain(':ripple="!timetableOptionUnavailable(\'no-distance-learning\')"')
        expect(source).toContain("'students-timetable-v2-options-card__option--selected': timetableNoDistanceLearningDraftSelected")
        expect(source).toContain("'students-timetable-v2-options-card__option--unavailable': timetableOptionUnavailable('no-distance-learning')")
        expect(source).toContain("'students-timetable-v2-options-card__option--loading': timetableCalculationLoading")
        expect(source).toContain(':tabindex="timetableOptionUnavailable(\'no-distance-learning\') ? -1 : 0"')
        expect(source).toContain(":aria-disabled=\"timetableOptionUnavailable('no-distance-learning') ? 'true' : 'false'\"")
        expect(source).toContain(":aria-pressed=\"timetableNoDistanceLearningDraftSelected ? 'true' : 'false'\"")
        expect(source).toContain('@click="toggleNoDistanceLearningTimetableOption"')
        expect(source).toContain('Kein Fernunterricht')
        expect(source).toContain('{{ noDistanceLearningTimetableCountFormatted }}')
        const studentStripIndex = source.indexOf('class="ttv2-strip"')
        const stepperIndex = source.indexOf('class="students-timetable-v2-stepper"')
        const visitedCoursesCardIndex = source.indexOf('students-timetable-v2-visited-courses-card')
        const courseSelectionCardsIndex = source.indexOf('<CourseSelectionCards')

        expect(stepperIndex).toBeGreaterThan(-1)
        expect(studentStripIndex).toBeGreaterThan(stepperIndex)
        expect(visitedCoursesCardIndex).toBeGreaterThan(studentStripIndex)
        expect(courseSelectionCardsIndex).toBeGreaterThan(visitedCoursesCardIndex)
        expect(source).toContain('<v-stepper')
        expect(source).toContain(':model-value="timetableV2StepNumber"')
        expect(source).toContain(':items="timetableV2StepperItems"')
        expect(source).toContain('hide-actions')
        expect(source).toContain("return TIMETABLE_V2_ROUTE_STEPS.indexOf(this.timetableV2Step) + 1")
        expect(source).toContain("{ title: 'Auswahl', value: 1 }")
        expect(source).toContain("{ title: 'Module', value: 2 }")
        expect(source).toContain("{ title: 'Stundenplan', value: 3 }")
        expect(source).toContain("{ title: 'Übernahme', value: 4 }")
        expect(source).toContain('class="students-timetable-v2-card-column students-timetable-v2-student-card-stack"')
        expect(source).toContain('v-if="courseCardsVisible" cols="12" class="students-timetable-v2-card-column"')
        expect(source).toContain('class="students-timetable-v2-card students-timetable-v2-visited-courses-card"')
        expect(source).toContain('class="students-timetable-v2-visited-courses-card__title"')
        expect(source).toContain('Besuchte Module')
        expect(source).toContain('v-for="course in visitedCourseItems"')
        expect(source).toContain(':color="course.color"')
        expect(source).toContain('{{ course.grade }}')
        expect(source).toContain("this.visitedCourseItem(course, 'completed')")
        expect(source).toContain("this.visitedCourseItem(course, 'failed')")
        expect(source).toMatch(/\.students-timetable-v2-visited-courses-card__title\s*\{[\s\S]*font-size: 1rem;[\s\S]*font-weight: 900;/u)
        expect(source).toMatch(/\.students-timetable-v2-visited-courses-card__list\s*\{[\s\S]*display: flex;[\s\S]*flex-wrap: wrap;/u)
        expect(source).toMatch(/\.students-timetable-v2-visited-courses-card__grade\s*\{[\s\S]*border-radius: 999px;[\s\S]*font-weight: 900;/u)
        expect(source).toContain('<CourseSelectionCards')
        expect(source).toContain(':cards="courseSelectionCards"')
        expect(source).toContain('class="ttv2-strip"')
        expect(source).toContain('class="ttv2-strip__selections"')
        expect(source).not.toContain('class="ttv2-strip__sel-label"')
        expect(source).toContain('role="group"')
        expect(source).toContain(':aria-label="item.label"')
        expect(source).toContain(':title="item.label"')
        expect(source).toContain('class="ttv2-strip__sel-chips"')
        expect(source).toContain('size="small"')
        expect(source).toContain('density="default"')
        expect(source).toMatch(/\.students-timetable-v2-stepper\s*\{[\s\S]*border: 1px solid rgba\(37, 99, 235, 0\.12\);[\s\S]*border-radius: 14px;/u)
        expect(source).toMatch(/\.students-timetable-v2-stepper :deep\(\.v-stepper-item__title\)\s*\{[\s\S]*font-size: 0\.82rem;[\s\S]*font-weight: 800;/u)
        expect(source).toMatch(/\.ttv2-strip__sel-group\s*\{[\s\S]*min-height: 42px;[\s\S]*padding: 6px 8px;[\s\S]*border: 1px solid rgba\(37, 99, 235, 0\.12\);[\s\S]*border-radius: 10px;[\s\S]*background: rgba\(248, 250, 252, 0\.86\);/u)
        expect(source).toMatch(/\.ttv2-strip__sel-chip\s*\{[\s\S]*min-height: 28px;[\s\S]*font-size: 0\.78rem !important;/u)
        expect(source).toMatch(/\.students-timetable-v2-selection\s*\{\s*display: grid;\s*gap: 5px;/u)
        expect(source).toMatch(/\.students-timetable-v2-selection__item\s*\{\s*display: grid;[\s\S]*grid-template-columns: minmax\(78px, 112px\) minmax\(0, 1fr\);[\s\S]*padding: 2px 0;/u)
        expect(source).toMatch(/@media \(max-width: 520px\) \{[\s\S]*\.students-timetable-v2-selection__item\s*\{[\s\S]*grid-template-columns: minmax\(0, 1fr\);/u)
        expect(source).toContain("title: 'Frühere Module'")
        expect(source).not.toContain('Alle vorgesehenen Module auswählen')
        expect(source).not.toContain('Alle vorgesehenen Module abwählen')
        expect(source).toContain("summaryChips: this.courseSelectionCardSummaryChips(this.storedMissingCourseCardSummary, 'success')")
        expect(source).toContain("title: 'Zusätzliche Module'")
        expect(courseSelectionCardsSource).toContain('v-for="summaryChip in card.summaryChips"')
        expect(courseSelectionCardsSource).toContain(':color="summaryChip.color"')
        expect(courseSelectionCardsSource).toMatch(/\.students-timetable-v2-completed-courses__item--missing \{[\s\S]*border-color: rgba\(22, 163, 74, 0\.18\);[\s\S]*background: rgba\(240, 253, 244, 0\.78\);/u)
        expect(courseSelectionCardsSource).not.toContain('Die Auswahl kann später noch verändert werden!')
        expect(source).toMatch(/\.students-timetable-v2-options-card__option \{[\s\S]*background: rgba\(248, 250, 252, 0\.96\);/u)
        expect(source).toMatch(/\.students-timetable-v2-options-card__option--unavailable \{[\s\S]*background: rgba\(241, 245, 249, 0\.96\);/u)
        expect(source).toMatch(/\.students-timetable-v2-options-card__option--loading \{[\s\S]*background: rgba\(248, 250, 252, 0\.98\);/u)
        const moreCoursesPanelActionsSource = source.slice(
            source.indexOf('v-else-if="timetableCalculationVisible" class="students-timetable-v2-calculation-card__more-course-actions"'),
            source.indexOf('v-else-if="adoptedTimetableCourseRemovalPendingVisible"'),
        )

        expect(moreCoursesPanelActionsSource).toContain('Abbruch')
        expect(moreCoursesPanelActionsSource).not.toContain('Anwenden')
        expect(source).toMatch(/size="large"\s+color="warning"\s+variant="tonal"\s+prepend-icon="mdi-close"\s+@click="cancelMoreCoursesCard">\s+Abbruch/u)
        expect(moreCoursesPanelActionsSource).not.toContain('@click="applyMoreCoursesSelection"')
        expect(source).toMatch(/v-else-if="adoptedTimetableCourseRemovalPendingVisible"[\s\S]*@click="cancelMoreCoursesCard">\s+Abbruch[\s\S]*color="error"\s+variant="flat"\s+prepend-icon="mdi-delete"\s+:disabled="!moreCoursesSelectionChanged"\s+@click="applyAdoptedTimetableCourseRemoval">\s+Löschen/u)
        expect(source).toContain('<span>{{ timetableCalculationCardTitleLabel }}</span>')
        expect(source).not.toContain('v-else-if="adoptedTimetableVisible" class="students-timetable-v2-calculation-card__actions"')
        expect(source).toMatch(/class="students-timetable-v2-result__meta"[\s\S]*prepend-icon="mdi-printer-outline"[\s\S]*:loading="pdfExporting"[\s\S]*@click="openAdoptedTimetablePrintDialog">\s+Drucken[\s\S]*v-if="adoptedTimetableSaveVisible"[\s\S]*prepend-icon="mdi-content-save-outline"[\s\S]*:loading="publishedTimetableSaving"[\s\S]*@click="saveAdoptedPublishedStudentTimetable"[\s\S]*Speichern für[\s\S]*prepend-icon="mdi-arrow-left"[\s\S]*Zurück/u)
        expect(source).not.toContain('{{ adoptedTimetableNumberLabel }}')
        expect(source).not.toContain('prepend-icon="mdi-check">')
        expect(source).toMatch(/<v-dialog v-model="printDialogVisible" persistent max-width="460">[\s\S]*Stundenplan drucken[\s\S]*label="Einzelne Wochen drucken"[\s\S]*label="Modulliste"[\s\S]*label="Modulübersicht"[\s\S]*@click="downloadAdoptedTimetablePdf">[\s\S]*Drucken/u)
        expect(source).toContain('class="students-timetable-v2-published-timetable-report"')
        expect(source).toContain('{{ adoptSelectedTimetableV2ButtonLabel }}')
        expect(source).not.toContain('Stundenplan übernehmen')
        expect(source).toContain("@click=\"adoptCurrentTimetableV2Result\"")
        expect(source).toContain("TIMETABLE_V2_ROUTE_STEPS = ['selection', 'course-review', 'timetable-calculation', 'timetable-adoption']")
        expect(source).toContain('@click="adoptedTimetableVisible ? backToTimetableCalculation() : backToCourseReview()"')
    })

    it('debounces stored timetable state saves so course toggles stay responsive', async () => {
        vi.useFakeTimers()

        const previousAxios = globalThis.axios
        const firstState = {
            timetableV2Selection: {
                courseSelections: {
                    'planned:D1': true,
                },
            },
        }
        const latestState = {
            timetableV2Selection: {
                courseSelections: {
                    'planned:D1': true,
                    'planned:M1': true,
                },
            },
        }
        const axiosMock = {
            put: vi.fn().mockResolvedValue({
                data: {
                    data: {
                        state: latestState,
                    },
                },
            }),
        }
        const context = timetableV2Context({
            saveStoredTimetableState: TimetableV2.methods.saveStoredTimetableState,
        })
        globalThis.axios = axiosMock

        try {
            TimetableV2.methods.saveStoredTimetableState.call(context, firstState)
            TimetableV2.methods.saveStoredTimetableState.call(context, latestState)

            expect(context.storedTimetableState).toBe(latestState)
            expect(context.storageRevision).toBe(2)
            expect(context.storedTimetableStateSaving).toBe(false)
            expect(axiosMock.put).not.toHaveBeenCalled()

            await vi.advanceTimersByTimeAsync(179)

            expect(axiosMock.put).not.toHaveBeenCalled()

            await vi.advanceTimersByTimeAsync(1)

            expect(axiosMock.put).toHaveBeenCalledOnce()
            expect(axiosMock.put).toHaveBeenCalledWith('/api/admin/students-timetables/timetable-v2-state', {
                state: latestState,
            })

            expect(context.storedTimetableStateSaving).toBe(false)
        } finally {
            globalThis.axios = previousAxios
            vi.useRealTimers()
        }
    })

    it('keeps a removed default semester course out of the selected card after a stale save response', async () => {
        vi.useFakeTimers()

        const previousAxios = globalThis.axios
        const savedState = {
            selection: {},
            timetableV2Selection: {
                courseSelections: {
                    'semester:M4': false,
                },
            },
            transferredStudentContext: null,
        }
        const staleResponseState = {
            selection: {},
            timetableV2Selection: {},
            transferredStudentContext: null,
        }
        const axiosMock = {
            put: vi.fn().mockResolvedValue({
                data: {
                    data: {
                        state: staleResponseState,
                    },
                },
            }),
        }
        const context = timetableV2Context({
            saveStoredTimetableState: TimetableV2.methods.saveStoredTimetableState,
            storedSemesterCourseItems: [
                { code: 'M4', hours: 3, key: 'M4', label: 'M4' },
                { code: 'D4', hours: 4, key: 'D4', label: 'D4' },
            ],
        })
        globalThis.axios = axiosMock

        try {
            TimetableV2.methods.saveStoredTimetableState.call(context, savedState)

            expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['D4'])

            await vi.advanceTimersByTimeAsync(180)
            await Promise.resolve()

            expect(axiosMock.put).toHaveBeenCalledOnce()
            expect(context.storedTimetableState).toBe(savedState)
            expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['D4'])
        } finally {
            globalThis.axios = previousAxios
            vi.useRealTimers()
        }
    })

    it('loads timetable v2 selection bootstrap data in one request', async () => {
        const previousAxios = globalThis.axios
        const storedState = {
            timetableV2Selection: {
                semester: 4,
            },
        }
        const courseGroups = [{ key: 'course-group-1' }]
        const subjects = [{ id: 1, json_code: 'D1' }]
        const axiosMock = {
            get: vi.fn(() => Promise.resolve({
                data: {
                    data: {
                        state: storedState,
                        course_groups: courseGroups,
                        subjects,
                    },
                },
            })),
        }
        const context = timetableV2Context({
            applyCourseLimitPreselection: vi.fn(),
        })
        globalThis.axios = axiosMock

        try {
            await TimetableV2.methods.loadTimetableV2SelectionBootstrap.call(context, '', {
                applyStoredState: true,
            })

            expect(axiosMock.get).toHaveBeenCalledOnce()
            expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/students-timetables/timetable-v2-selection-bootstrap', {
                params: {},
            })
            expect(context.storedTimetableState).toBe(storedState)
            expect(context.storageRevision).toBe(1)
            expect(context.courseGroups).toBe(courseGroups)
            expect(context.courseGroupsRevision).toBe(1)
            expect(context.courseGroupsLoaded).toBe(true)
            expect(context.subjectRows).toBe(subjects)
            expect(context.selectionBootstrapLoading).toBe(false)
            expect(context.applyCourseLimitPreselection).toHaveBeenCalledOnce()
        } finally {
            globalThis.axios = previousAxios
        }
    })

    it('applies bootstrap student overview locally without scheduling an immediate state save', () => {
        const context = timetableV2Context({
            storedTimetableState: {
                timetableV2Selection: {
                    semester: 4,
                },
                transferredStudentContext: {
                    student: {
                        studentCode: '200',
                    },
                },
            },
            storedTimetableStudentContext: {
                student: {
                    studentCode: '200',
                },
            },
            saveStoredTimetableState: vi.fn(),
            resetDraftCourseSelections: vi.fn(),
            applyCourseLimitPreselection: vi.fn(),
        })

        TimetableV2.methods.applyStoredStudentOverviewSummary.call(context, '200', {
            student: {
                student_code: '200',
                religion: 'Ris',
            },
            selection: {
                semester: 4,
                religion: 'Ris',
            },
            completed_courses: [
                {
                    code: 'D1',
                    grade: '1',
                },
            ],
            missing_courses: [],
            proposed_courses: [],
            additional_courses: [],
            automatic_course_selection: {
                sections: [],
            },
        }, 'request-key', {
            persistState: false,
        })

        expect(context.saveStoredTimetableState).not.toHaveBeenCalled()
        expect(context.resetDraftCourseSelections).toHaveBeenCalledOnce()
        expect(context.storageRevision).toBe(1)
        expect(context.studentOverviewLoadedRequestKey).toBe('request-key')
        expect(context.storedTimetableState.transferredStudentContext.student.religion).toBe('Ris')
        expect(context.storedTimetableState.transferredStudentContext.courses.completed[0].code).toBe('D1')
        expect(context.applyCourseLimitPreselection).toHaveBeenCalledOnce()
    })

    it('invalidates offered course cache when course groups are replaced', () => {
        const context = timetableV2Context({
            courseGroupsRevision: 2,
            offeredCourseItemsCache: {
                '2|D1': [{ key: 'old-offer' }],
            },
        })
        const courseGroups = [{ key: 'new-offer' }]

        TimetableV2.methods.replaceCourseGroups.call(context, courseGroups)

        expect(context.courseGroups).toBe(courseGroups)
        expect(context.courseGroupsRevision).toBe(3)
        expect(context.offeredCourseItemsCache).toEqual({})
        expect(context.offeredCourseItemsCacheCourseGroups).toBe(courseGroups)
    })

    it('loads stored student overview through the selection bootstrap without a recursive reload', async () => {
        const previousAxios = globalThis.axios
        const axiosMock = {
            get: vi.fn(() => Promise.resolve({
                data: {
                    data: {
                        state: null,
                        course_groups: [],
                        subjects: [],
                        student_overview: {
                            student: {
                                student_code: '200',
                                religion: 'Ris',
                            },
                            selection: {
                                semester: 4,
                                religion: 'Ris',
                                language: 'F',
                                branch: 'gymnasial',
                                arts_subject: 'ME',
                            },
                            completed_courses: [
                                {
                                    code: 'D1',
                                    grade: '1',
                                },
                            ],
                            missing_courses: [],
                            proposed_courses: [],
                            additional_courses: [],
                            automatic_course_selection: {
                                sections: [],
                            },
                        },
                    },
                },
            })),
        }
        const context = timetableV2Context({
            storedTimetableState: {
                timetableV2Selection: {
                    semester: 4,
                },
                transferredStudentContext: {
                    student: {
                        studentCode: '200',
                    },
                },
            },
            storedTimetableStudentContext: {
                student: {
                    studentCode: '200',
                },
            },
            applyStoredStudentOverviewSummary: vi.fn(),
            loadTimetableV2SelectionBootstrap: TimetableV2.methods.loadTimetableV2SelectionBootstrap,
        })
        globalThis.axios = axiosMock

        try {
            await TimetableV2.methods.loadStoredStudentOverview.call(context, '200', {
                strictSelection: false,
            })

            expect(axiosMock.get).toHaveBeenCalledOnce()
            expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/students-timetables/timetable-v2-selection-bootstrap', {
                params: {
                    student_code: '200',
                    strict_selection: 0,
                    selection: {
                        semester: 4,
                    },
                },
            })
            expect(context.loadStoredStudentOverview).not.toHaveBeenCalled()
            expect(context.applyStoredStudentOverviewSummary).toHaveBeenCalledOnce()
            expect(context.applyStoredStudentOverviewSummary.mock.calls[0][0]).toBe('200')
            expect(context.applyStoredStudentOverviewSummary.mock.calls[0][1].student.religion).toBe('Ris')
            expect(context.applyStoredStudentOverviewSummary.mock.calls[0][2]).toContain('"studentCode":"200"')
        } finally {
            globalThis.axios = previousAxios
        }
    })

    it('formats the no Saturday timetable count for the options card', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                no_saturday_timetable_count: 12,
            },
        })

        expect(context.noSaturdayTimetableCount).toBe(12)
        expect(context.noSaturdayTimetableCountFormatted).toBe('12')
        expect(context.selectedTimetableV2SaturdayFreeTestLabel).toBe('sa-free: yes')
    })

    it('marks the Saturday-free test as no when no timetable is Saturday-free', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                no_saturday_timetable_count: 0,
            },
        })

        expect(context.noSaturdayTimetableCount).toBe(0)
        expect(context.selectedTimetableV2SaturdayFreeTestLabel).toBe('sa-free: no')
    })

    it('analyses the booked E2-1U-NI compact course for the tests card', () => {
        const context = timetableV2Context({
            schoolHours: [
                { hour: 1, from: '09:00:00', until: '09:50:00' },
                { hour: 2, from: '09:50:00', until: '10:35:00' },
                { hour: 3, from: '10:35:00', until: '11:25:00' },
                { hour: 4, from: '11:25:00', until: '12:10:00' },
            ],
            timetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '6-1': {
                            code: 'E2',
                            sourceLabel: 'E - 2 - 1U - NI',
                            dateRangeLabel: '09.05. - 11.07.',
                            courseGroup: { class_name: 'E - 2 - 1U - NI', hour: 1, is_kompaktunterricht: true, weekday: 6 },
                        },
                        '6-2': {
                            code: 'E2',
                            sourceLabel: 'E - 2 - 1U - NI',
                            dateRangeLabel: '09.05. - 11.07.',
                            courseGroup: { class_name: 'E - 2 - 1U - NI', hour: 2, is_kompaktunterricht: true, weekday: 6 },
                        },
                        '6-3': {
                            code: 'E2',
                            sourceLabel: 'E - 2 - 1U - NI',
                            dateRangeLabel: '09.05. - 11.07.',
                            courseGroup: { class_name: 'E - 2 - 1U - NI', hour: 3, is_kompaktunterricht: true, weekday: 6 },
                        },
                        '6-4': {
                            code: 'E2',
                            sourceLabel: 'E - 2 - 1U - NI',
                            dateRangeLabel: '09.05. - 04.07.',
                            courseGroup: { class_name: 'E - 2 - 1U - NI', hour: 4, is_kompaktunterricht: true, weekday: 6 },
                        },
                    },
                },
            },
        })

        expect(context.selectedTimetableV2TestCourseAnalysisItems).toEqual([
            'E - 2 - 1U - NI sa-only: yes',
        ])
    })

    it('analyses E2-1U-NI directly from booked app course offers for the tests card', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { code: 'E2', label: 'E2' },
            ],
            offeredCourseItemsForSelectedCourse: () => [
                {
                    name: 'E - 2 - 1U - NI',
                    selectionKey: 'e2-ni',
                    scheduleSlots: [
                        { dateRangeLabel: '09.05. - 11.07.', from: '09:00', hour: 1, until: '09:50', weekday: 6 },
                        { dateRangeLabel: '09.05. - 11.07.', from: '09:50', hour: 2, until: '10:35', weekday: 6 },
                        { dateRangeLabel: '09.05. - 11.07.', from: '10:35', hour: 3, until: '11:25', weekday: 6 },
                        { dateRangeLabel: '09.05. - 04.07.', from: '11:25', hour: 4, until: '12:10', weekday: 6 },
                    ],
                },
                {
                    name: 'E - 2 - 1U - OTHER',
                    selectionKey: 'e2-other',
                    scheduleSlots: [
                        { dateRangeLabel: '09.05. - 11.07.', from: '09:00', hour: 1, until: '09:50', weekday: 5 },
                    ],
                },
            ],
            offeredCourseSelected: (offeredCourse) => offeredCourse.selectionKey !== 'e2-other',
        })

        expect(context.selectedTimetableV2TestCourseAnalysisItems).toEqual([
            'E - 2 - 1U - NI sa-only: yes',
        ])
    })

    it('analyses E2-1U-NI directly from loaded app course groups for the tests card', () => {
        const context = timetableV2Context({
            courseGroups: [
                {
                    class_name: 'E - 2 - 1U - NI',
                    course: 'E',
                    display_label: 'E - 2 - 1U - NI',
                    first_date: '2026-05-09',
                    hour: 1,
                    is_kompaktunterricht: true,
                    last_date: '2026-07-11',
                    semester: 2,
                    student_group: '1U',
                    title: 'E2',
                    weekday: 6,
                },
                {
                    class_name: 'E - 2 - 1U - NI',
                    course: 'E',
                    display_label: 'E - 2 - 1U - NI',
                    first_date: '2026-05-09',
                    hour: 2,
                    is_kompaktunterricht: true,
                    last_date: '2026-07-11',
                    semester: 2,
                    student_group: '1U',
                    title: 'E2',
                    weekday: 6,
                },
                {
                    class_name: 'E - 2 - 1U - NI',
                    course: 'E',
                    display_label: 'E - 2 - 1U - NI',
                    first_date: '2026-05-09',
                    hour: 3,
                    is_kompaktunterricht: true,
                    last_date: '2026-07-11',
                    semester: 2,
                    student_group: '1U',
                    title: 'E2',
                    weekday: 6,
                },
                {
                    class_name: 'E - 2 - 1U - NI',
                    course: 'E',
                    display_label: 'E - 2 - 1U - NI',
                    first_date: '2026-05-09',
                    hour: 4,
                    is_kompaktunterricht: true,
                    last_date: '2026-07-04',
                    semester: 2,
                    student_group: '1U',
                    title: 'E2',
                    weekday: 6,
                },
            ],
            schoolHours: [
                { hour: 1, from: '09:00:00', until: '09:50:00' },
                { hour: 2, from: '09:50:00', until: '10:35:00' },
                { hour: 3, from: '10:35:00', until: '11:25:00' },
                { hour: 4, from: '11:25:00', until: '12:10:00' },
            ],
        })

        expect(context.selectedTimetableV2TestCourseAnalysisItems).toEqual([
            'E - 2 - 1U - NI sa-only: yes',
        ])
    })

    it('marks E2-1U-NI as not Saturday-only when the booked course uses another weekday', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { code: 'E2', label: 'E2' },
            ],
            offeredCourseItemsForSelectedCourse: () => [
                {
                    name: 'E - 2 - 1U - NI',
                    selectionKey: 'e2-ni',
                    scheduleSlots: [
                        { dateRangeLabel: '09.05. - 11.07.', from: '09:00', hour: 1, until: '09:50', weekday: 6 },
                        { dateRangeLabel: '09.05. - 11.07.', from: '09:50', hour: 2, until: '10:35', weekday: 5 },
                    ],
                },
            ],
        })

        expect(context.selectedTimetableV2TestCourseAnalysisItems).toEqual([
            'E - 2 - 1U - NI sa-only: no',
        ])
    })

    it('explains why the E2-1U-NI Saturday-only course is unavailable in the tests card', () => {
        const courseGroups = [
            {
                class_name: 'E - 2 - 1U - NI',
                course: 'E2',
                display_label: 'E - 2 - 1U - NI',
                first_date: '2026-05-09',
                hour: 1,
                is_kompaktunterricht: true,
                key: 'e2-ni-1',
                last_date: '2026-07-11',
                semester: 2,
                student_group: '1U',
                teacher: 'NI',
                title: 'E2',
                weekday: 6,
            },
        ]
        const context = timetableV2Context({
            courseGroups,
            courseGroupsLoaded: true,
            storedMissingCourseCardItems: [
                { code: 'E2', key: 'E2', label: 'E2' },
            ],
            timetableNoSaturdaySelected: true,
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'E2')
        const offeredCourse = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)[0]
        const availabilityKey = TimetableV2.methods.moreCourseOfferAvailabilityKey.call(context, moreCourse, offeredCourse)

        context.moreCourseAvailabilityByKey = {
            [availabilityKey]: false,
        }

        expect(context.selectedTimetableV2TestCourseAnalysisItems).toEqual(expect.arrayContaining([
            expect.stringMatching(/sa-only: yes$/u),
            'no-saturday-filter: yes',
            expect.stringMatching(/availability: no$/u),
            'reason: no-saturday filter blocks this Saturday-only course',
        ]))
    })

    it('shows D5-3R-SHAM and E5-3R-HÖF dates and hours in the tests card', () => {
        const context = timetableV2Context({
            schoolHours: [
                { hour: 4, from: '10:00:00', until: '10:45:00' },
                { hour: 9, from: '16:20:00', until: '17:05:00' },
                { hour: 12, from: '18:45:00', until: '19:30:00' },
                { hour: 13, from: '19:30:00', until: '20:15:00' },
                { hour: 14, from: '20:25:00', until: '21:10:00' },
                { hour: 15, from: '21:10:00', until: '21:55:00' },
                { hour: 16, from: '21:55:00', until: '22:40:00' },
            ],
            courseGroups: [
                {
                    class_name: 'D5-3R-SHAM',
                    dates: [
                        '2026-02-24',
                        '2026-03-10',
                        '2026-03-24',
                        '2026-04-07',
                        '2026-04-21',
                        '2026-05-05',
                        '2026-05-12',
                        '2026-05-19',
                        '2026-06-02',
                        '2026-06-16',
                        '2026-06-30',
                    ],
                    display_label: 'D5-3R-SHAM',
                    hour: 14,
                    recurrence_interval: 2,
                    title: 'D5',
                    weekday: 2,
                },
                {
                    class_name: 'D5-3R-SHAM',
                    dates: [
                        '2026-02-17',
                        '2026-02-24',
                        '2026-03-03',
                        '2026-03-10',
                        '2026-03-17',
                        '2026-03-24',
                        '2026-04-07',
                        '2026-04-14',
                        '2026-04-21',
                        '2026-04-28',
                        '2026-05-05',
                        '2026-05-12',
                        '2026-05-19',
                        '2026-05-26',
                        '2026-06-02',
                        '2026-06-09',
                        '2026-06-16',
                        '2026-06-23',
                        '2026-06-30',
                        '2026-07-07',
                    ],
                    display_label: 'D5-3R-SHAM',
                    hour: 15,
                    title: 'D5',
                    weekday: 2,
                },
                {
                    class_name: 'M4-3R-SCHM',
                    dates: ['2026-02-20', '2026-03-06'],
                    display_label: 'M4-3R-SCHM',
                    hour: 4,
                    recurrence_interval: 2,
                    title: 'M4',
                    weekday: 5,
                },
                {
                    class_name: 'M5-3R-SCHM',
                    dates: ['2026-02-27', '2026-03-13'],
                    display_label: 'M5-3R-SCHM',
                    hour: 9,
                    title: 'M5',
                    weekday: 5,
                },
                {
                    class_name: 'E6-3R-HÖF',
                    dates: ['2026-02-19', '2026-03-05'],
                    display_label: 'E6-3R-HÖF',
                    hour: 16,
                    title: 'E6',
                    weekday: 4,
                },
            ],
            timetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '2-12': {
                            sourceLabel: 'D5-3R-SHAM',
                            courseGroup: {
                                dates: ['2026-04-28', '2026-05-05'],
                                hour: 12,
                                weekday: 2,
                            },
                        },
                        '4-12': {
                            sourceLabel: 'D5-3R-SHAM',
                            courseGroup: {
                                dates: ['2026-05-07', '2026-05-14'],
                                hour: 12,
                                weekday: 4,
                            },
                        },
                        '4-13': {
                            sourceLabel: 'D5-3R-SHAM',
                            courseGroup: {
                                dates: ['2026-05-07', '2026-05-14'],
                                hour: 13,
                                weekday: 4,
                            },
                        },
                        '3-12': {
                            sourceLabel: 'E5-3R-HÖF',
                            courseGroup: {
                                dates: ['2026-05-06', '2026-05-13'],
                                distanceLearning: true,
                                hour: 12,
                                weekday: 3,
                            },
                        },
                        '3-13': {
                            sourceLabel: 'E5-3R-HÖF',
                            courseGroup: {
                                dates: ['2026-05-06', '2026-05-13'],
                                distanceLearning: true,
                                hour: 13,
                                weekday: 3,
                            },
                        },
                        '2-14': {
                            sourceLabel: 'PH1-5C-DOM',
                            courseGroup: {
                                dates: ['2026-04-28'],
                                hour: 14,
                                weekday: 2,
                            },
                            conflicts: [
                                {
                                    sourceLabel: 'E5-3R-HÖF',
                                    courseGroup: {
                                        dates: ['2026-02-17', '2026-03-03', '2026-03-17', '2026-04-14'],
                                        hour: 14,
                                        isKompaktunterricht: true,
                                        recurrence_interval: 2,
                                        weekday: 2,
                                    },
                                    isKompaktunterrichtCourse: true,
                                },
                            ],
                        },
                        '5-12': {
                            sourceLabel: 'D5-3R-OTHER',
                            courseGroup: {
                                dates: ['2026-05-08'],
                                hour: 12,
                                weekday: 5,
                            },
                        },
                    },
                },
            },
            timetableV2Step: 'timetable-calculation',
        })

        expect(context.selectedTimetableV2TestsCardScheduleSlots).toHaveLength(2)
        expect(context.selectedTimetableV2TestsCardScheduleLabel).toBe('Di 14. 2-wöchig 20:25-21:10 (24.02., 10.03., 24.03., 07.04., 21.04., 05.05., 12.05., 19.05., 02.06., 16.06., 30.06.), Di 15. 21:10-21:55 (17.02., 24.02., 03.03., 10.03., 17.03., 24.03., 07.04., 14.04., 21.04., 28.04., 05.05., 12.05., 19.05., 26.05., 02.06., 09.06., 16.06., 23.06., 30.06., 07.07.)')
        expect(context.selectedTimetableV2TestsCardMatchingScheduleSlots).toEqual([])
        expect(context.selectedTimetableV2TestsCardScheduleComparison).toEqual({
            color: 'warning',
            label: 'Gleiche Tage/Uhrzeiten/Daten: Nein',
        })
        expect(context.selectedTimetableV2TestsCardCourseScheduleItems).toEqual([
            {
                displayLabel: '1. D - 5 - 3R - SHAM',
                key: 'D5-3R-SHAM',
                label: 'D5-3R-SHAM',
                scheduleLabel: 'Di 14. 2-wöchig 20:25-21:10 (24.02., 10.03., 24.03., 07.04., 21.04., 05.05., 12.05., 19.05., 02.06., 16.06., 30.06.), Di 15. 21:10-21:55 (17.02., 24.02., 03.03., 10.03., 17.03., 24.03., 07.04., 14.04., 21.04., 28.04., 05.05., 12.05., 19.05., 26.05., 02.06., 09.06., 16.06., 23.06., 30.06., 07.07.)',
                typeLabel: 'Kompakt',
            },
            {
                displayLabel: '2. E - 5 - 3R - HÖF',
                key: 'E5-3R-HOEF',
                label: 'E5-3R-HÖF',
                scheduleLabel: 'Di 14. 2-wöchig 20:25-21:10 (17.02., 03.03., 17.03., 14.04.), Mi 12.-13. 18:45-20:15 (06.05., 13.05.)',
                typeLabel: 'Kompakt',
            },
            {
                displayLabel: '3. M - 4 - 3R - SCHM',
                key: 'M4-3R-SCHM',
                label: 'M4-3R-SCHM',
                scheduleLabel: 'Fr 4. 2-wöchig 10:00-10:45 (20.02., 06.03.)',
                typeLabel: 'Kompakt',
            },
            {
                displayLabel: '4. M - 5 - 3R - SCHM',
                key: 'M5-3R-SCHM',
                label: 'M5-3R-SCHM',
                scheduleLabel: 'Fr 9. 16:20-17:05 (27.02., 13.03.)',
                typeLabel: 'Kompakt',
            },
            {
                displayLabel: '5. E - 6 - 3R - HÖF',
                key: 'E6-3R-HOEF',
                label: 'E6-3R-HÖF',
                scheduleLabel: 'Do 16. 21:55-22:40 (19.02., 05.03.)',
                typeLabel: 'Kompakt',
            },
        ])
    })

    it('answers when D5-3R-SHAM and E5-3R-HÖF share the same day hour and dates in the tests card', () => {
        const context = timetableV2Context({
            schoolHours: [
                { hour: 12, from: '18:45:00', until: '19:30:00' },
                { hour: 13, from: '19:30:00', until: '20:15:00' },
            ],
            timetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '2-12-d5': {
                            key: 'd5-shared-12',
                            sourceLabel: 'D5-3R-SHAM',
                            courseGroup: {
                                dates: ['2026-04-28', '2026-05-05'],
                                hour: 12,
                                isKompaktunterricht: true,
                                weekday: 2,
                            },
                        },
                        '2-13-d5': {
                            key: 'd5-shared-13',
                            sourceLabel: 'D5-3R-SHAM',
                            courseGroup: {
                                dates: ['2026-04-28', '2026-05-05'],
                                hour: 13,
                                isKompaktunterricht: true,
                                weekday: 2,
                            },
                        },
                        '2-12-e5': {
                            key: 'e5-shared-12',
                            sourceLabel: 'E5-3R-HÖF',
                            courseGroup: {
                                dates: ['2026-04-28', '2026-05-12'],
                                hour: 12,
                                weekday: 2,
                            },
                        },
                        '3-12-e5': {
                            key: 'e5-other',
                            sourceLabel: 'E5-3R-HÖF',
                            courseGroup: {
                                dates: ['2026-05-06'],
                                hour: 12,
                                weekday: 3,
                            },
                        },
                    },
                },
            },
            timetableV2Step: 'timetable-calculation',
        })

        expect(context.selectedTimetableV2TestsCardMatchingScheduleSlots).toEqual([
            {
                dateLabels: ['28.04.'],
                dateRangeLabel: '28.04.',
                from: '18:45',
                hour: 12,
                recurrenceLabel: '',
                until: '19:30',
                weekday: 2,
            },
        ])
        expect(context.selectedTimetableV2TestsCardScheduleComparison).toEqual({
            color: 'success',
            label: 'Gleiche Tage/Uhrzeiten/Daten: Ja - E5-3R-HÖF: Di 12. 18:45-19:30 (28.04.)',
        })
        expect(context.selectedTimetableV2TestsCardCourseScheduleItems.map(({ key, typeLabel }) => ({ key, typeLabel }))).toEqual([
            {
                key: 'D5-3R-SHAM',
                typeLabel: 'Kompakt',
            },
            {
                key: 'E5-3R-HOEF',
                typeLabel: 'Kompakt',
            },
            {
                key: 'M4-3R-SCHM',
                typeLabel: 'Kompakt',
            },
            {
                key: 'M5-3R-SCHM',
                typeLabel: 'Kompakt',
            },
            {
                key: 'E6-3R-HOEF',
                typeLabel: 'Kompakt',
            },
        ])
    })

    it('answers when D5-3R-SHAM and M5-3R-SCHM share the same day hour and dates in the tests card', () => {
        const context = timetableV2Context({
            schoolHours: [
                { hour: 14, from: '20:25:00', until: '21:10:00' },
            ],
            timetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '2-14-d5': {
                            key: 'd5-shared-14',
                            sourceLabel: 'D5-3R-SHAM',
                            courseGroup: {
                                dates: ['2026-05-05'],
                                hour: 14,
                                weekday: 2,
                            },
                        },
                        '2-14-m5': {
                            key: 'm5-shared-14',
                            sourceLabel: 'M5-3R-SCHM',
                            courseGroup: {
                                dates: ['2026-05-05', '2026-05-12'],
                                hour: 14,
                                weekday: 2,
                            },
                        },
                    },
                },
            },
            timetableV2Step: 'timetable-calculation',
        })

        expect(context.selectedTimetableV2TestsCardMatchingCourseScheduleItems).toEqual([
            expect.objectContaining({
                key: 'M5-3R-SCHM',
                label: 'M5-3R-SCHM',
                matchingSlots: [
                    {
                        dateLabels: ['05.05.'],
                        dateRangeLabel: '05.05.',
                        from: '20:25',
                        hour: 14,
                        recurrenceLabel: '',
                        until: '21:10',
                        weekday: 2,
                    },
                ],
            }),
        ])
        expect(context.selectedTimetableV2TestsCardScheduleComparison).toEqual({
            color: 'success',
            label: 'Gleiche Tage/Uhrzeiten/Daten: Ja - M5-3R-SCHM: Di 14. 20:25-21:10 (05.05.)',
        })
    })

    it('formats the max free days timetable count and maximum label for the options card', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                quality_counters: [
                    {
                        key: 'free_days',
                        count: 7,
                        best_value: 2,
                        best_label: '2 freie Tage',
                    },
                ],
            },
        })

        expect(context.maxFreeDaysQualityCounter).toEqual({
            key: 'free_days',
            count: 7,
            best_value: 2,
            best_label: '2 freie Tage',
        })
        expect(context.maxFreeDaysTimetableCount).toBe(7)
        expect(context.maxFreeDaysTimetableCountFormatted).toBe('7')
        expect(context.maxFreeDaysOptionMaximumLabel).toBe('Max 2 freie Tage')
        expect(context.maxFreeDaysSelectedOptionLabel).toBe('Max 2 freie Tage')
    })

    it('formats the no distance learning timetable count for the options card', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                quality_counters: [
                    {
                        key: 'avoid_distance_learning',
                        count: 8,
                    },
                ],
            },
        })

        expect(context.noDistanceLearningQualityCounter).toEqual({
            key: 'avoid_distance_learning',
            count: 8,
        })
        expect(context.noDistanceLearningTimetableCount).toBe(8)
        expect(context.noDistanceLearningTimetableCountFormatted).toBe('8')
    })

    it('formats the starts-from-period-10 timetable count for the options card', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                quality_counters: [
                    {
                        key: 'starts_from_period_10',
                        count: 6,
                    },
                ],
            },
        })

        expect(context.startsFromPeriod10QualityCounter).toEqual({
            key: 'starts_from_period_10',
            count: 6,
        })
        expect(context.startsFromPeriod10TimetableCount).toBe(6)
        expect(context.startsFromPeriod10TimetableCountFormatted).toBe('6')
    })

    it('uses the filtered Saturday-free counter when max free days is selected', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                no_saturday_timetable_count: 12,
                quality_counters: [
                    {
                        key: 'saturday_free',
                        count: 5,
                    },
                ],
            },
            timetableMaxFreeDaysSelected: true,
        })

        expect(context.saturdayFreeQualityCounter).toEqual({
            key: 'saturday_free',
            count: 5,
        })
        expect(context.noSaturdayTimetableCount).toBe(5)
        expect(context.noSaturdayTimetableCountFormatted).toBe('5')
    })

    it('requests the max free days counter with timetable calculations', () => {
        const context = timetableV2Context()
        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.include_quality_counters).toBe(true)
        expect(payload.selected_quality_criteria_required).toBe(false)
        expect(payload.selected_quality_criterion_keys).toEqual([])
        expect(payload.evaluation_criteria).toEqual([
            {
                key: 'free_days',
                enabled: true,
                priority: 1,
                option: null,
            },
            {
                key: 'avoid_distance_learning',
                enabled: true,
                priority: 2,
                option: 'none',
            },
            {
                key: 'saturday_free',
                enabled: true,
                priority: 3,
                option: null,
            },
            {
                key: 'starts_from_period_10',
                enabled: true,
                priority: 4,
                option: null,
            },
        ])
    })

    it('stages the max free days option without recalculating timetables', () => {
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
                    maxFreeDays: false,
                    noDistanceLearning: false,
                    noSaturday: false,
                    startsFromPeriod10: false,
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationResult: {
                selected_quality_criteria_count: 3,
                quality_counters: [
                    {
                        key: 'free_days',
                        count: 3,
                    },
                ],
            },
            timetableCalculationSelectedNumber: 4,
            timetableOptionsCardVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.toggleMaxFreeDaysTimetableOption.call(context)

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.timetableMaxFreeDaysDraftSelected).toBe(true)
        expect(context.timetableMaxFreeDaysSelected).toBe(false)
        expect(context.timetableOptionsChanged).toBe(true)
        expect(context.timetableOptionsCardVisible).toBe(true)
        expect(context.timetableCalculationSelectedNumber).toBe(4)
        expect(context.moreCourseAvailabilityByKey).toEqual({
            'additional:INF2': true,
        })
        expect(context.moreCourseAvailabilitySignature).toBe('previous')
        expect(context.selectedTimetableV2ResultCount).toBe(0)
        expect(payload.selected_quality_criteria_required).toBe(false)
        expect(payload.selected_quality_criterion_keys).toEqual([])
        expect(payload.evaluation_criteria).toEqual([
            {
                key: 'free_days',
                enabled: true,
                priority: 1,
                option: null,
            },
            {
                key: 'avoid_distance_learning',
                enabled: true,
                priority: 2,
                option: 'none',
            },
            {
                key: 'saturday_free',
                enabled: true,
                priority: 3,
                option: null,
            },
            {
                key: 'starts_from_period_10',
                enabled: true,
                priority: 4,
                option: null,
            },
        ])
        expect(saveStoredTimetableState).not.toHaveBeenCalled()
        expect(calculateTimetables).not.toHaveBeenCalled()
    })

    it('stages the no distance learning option without recalculating timetables', () => {
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
                    maxFreeDays: false,
                    noDistanceLearning: false,
                    noSaturday: false,
                    startsFromPeriod10: false,
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationResult: {
                selected_quality_criteria_count: 4,
                quality_counters: [
                    {
                        key: 'avoid_distance_learning',
                        count: 5,
                    },
                ],
            },
            timetableCalculationSelectedNumber: 3,
            timetableOptionsCardVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.toggleNoDistanceLearningTimetableOption.call(context)

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.timetableNoDistanceLearningDraftSelected).toBe(true)
        expect(context.timetableNoDistanceLearningSelected).toBe(false)
        expect(context.timetableOptionsChanged).toBe(true)
        expect(context.timetableOptionsCardVisible).toBe(true)
        expect(context.timetableCalculationSelectedNumber).toBe(3)
        expect(context.moreCourseAvailabilityByKey).toEqual({
            'additional:INF2': true,
        })
        expect(context.moreCourseAvailabilitySignature).toBe('previous')
        expect(context.selectedTimetableV2ResultCount).toBe(0)
        expect(payload.selected_quality_criteria_required).toBe(false)
        expect(payload.selected_quality_criterion_keys).toEqual([])
        expect(saveStoredTimetableState).not.toHaveBeenCalled()
        expect(calculateTimetables).not.toHaveBeenCalled()
    })

    it('accumulates timetable option selections while staging changes', () => {
        const calculateTimetables = vi.fn()
        const context = timetableV2Context({
            calculateTimetables,
            timetableCalculationResult: {
                no_saturday_timetable_count: 4,
                selected_quality_criteria_count: 4,
                quality_counters: [
                    { key: 'free_days', count: 4 },
                    { key: 'avoid_distance_learning', count: 4 },
                    { key: 'starts_from_period_10', count: 4 },
                ],
            },
            timetableOptionsCardVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'max-free-days')).toBe(false)

        TimetableV2.methods.toggleNoSaturdayTimetableOption.call(context)

        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'no-saturday')).toBe(false)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'starts-from-period-10')).toBe(false)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'max-free-days')).toBe(false)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'no-distance-learning')).toBe(false)

        TimetableV2.methods.toggleStartsFromPeriod10TimetableOption.call(context)

        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(context.timetableStartsFromPeriod10DraftSelected).toBe(true)
        expect(context.timetableMaxFreeDaysSelected).toBe(false)
        expect(context.timetableNoDistanceLearningSelected).toBe(false)
        expect(calculateTimetables).not.toHaveBeenCalled()

        TimetableV2.methods.toggleMaxFreeDaysTimetableOption.call(context)

        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(context.timetableStartsFromPeriod10DraftSelected).toBe(true)
        expect(context.timetableStartsFromPeriod10Selected).toBe(false)
        expect(context.timetableMaxFreeDaysDraftSelected).toBe(true)
        expect(context.timetableMaxFreeDaysSelected).toBe(false)
        expect(calculateTimetables).not.toHaveBeenCalled()

        TimetableV2.methods.toggleNoDistanceLearningTimetableOption.call(context)

        expect(context.timetableMaxFreeDaysDraftSelected).toBe(true)
        expect(context.timetableMaxFreeDaysSelected).toBe(false)
        expect(context.timetableNoDistanceLearningDraftSelected).toBe(true)
        expect(context.timetableNoDistanceLearningSelected).toBe(false)
        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'no-saturday',
                label: 'Kein Samstag',
            },
            {
                key: 'starts-from-period-10',
                label: 'Erst ab 10. Stunde',
            },
            {
                key: 'no-distance-learning',
                label: 'Kein Fernunterricht',
            },
            {
                key: 'max-free-days',
                label: 'Max freie Tage',
            },
        ])
        expect(calculateTimetables).not.toHaveBeenCalled()
    })

    it('disables all timetable options when only conflict timetables are available', () => {
        const calculateTimetables = vi.fn()
        const context = timetableV2Context({
            calculateTimetables,
            maxFreeDaysTimetableCount: 64,
            noDistanceLearningTimetableCount: 6,
            startsFromPeriod10TimetableCount: 648,
            timetableCalculationResult: {
                conflict_timetable_count: 2592,
                full_green_timetable_count: 0,
                green_timetable_count: 0,
                selected_timetable: {
                    number: 1,
                    type: 'conflict',
                },
            },
            timetableCalculationVisible: true,
            timetableStartMode: 'student',
            timetableV2Step: 'timetable-calculation',
        })

        expect(context.timetableOptionsUnavailable).toBe(true)
        expect(context.selectedTimetableV2TitleLabel).toBe('Stundenplan mit Überschneidung')
        expect(context.selectedTimetableV2RestartButtonInHeaderVisible).toBe(true)

        TimetableV2.methods.toggleTimetableOptionsCard.call(context)

        expect(context.timetableOptionsCardVisible).toBe(false)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'no-saturday')).toBe(true)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'starts-from-period-10')).toBe(true)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'max-free-days')).toBe(true)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'no-distance-learning')).toBe(true)

        TimetableV2.methods.toggleStartsFromPeriod10TimetableOption.call(context)
        TimetableV2.methods.toggleMaxFreeDaysTimetableOption.call(context)
        TimetableV2.methods.toggleNoDistanceLearningTimetableOption.call(context)
        TimetableV2.methods.applyTimetableOptions.call(context)

        expect(context.timetableStartsFromPeriod10DraftSelected).toBe(false)
        expect(context.timetableMaxFreeDaysDraftSelected).toBe(false)
        expect(context.timetableNoDistanceLearningDraftSelected).toBe(false)
        expect(calculateTimetables).not.toHaveBeenCalled()
    })

    it('marks the timetable options button unavailable when every option counter is zero', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                full_green_timetable_count: 1,
                green_timetable_count: 0,
                conflict_timetable_count: 0,
                no_saturday_timetable_count: 0,
                quality_counters: [
                    { key: 'free_days', count: 0 },
                    { key: 'avoid_distance_learning', count: 0 },
                    { key: 'starts_from_period_10', count: 0 },
                ],
            },
            timetableCalculationVisible: true,
            timetableStartMode: 'student',
            timetableV2Step: 'timetable-calculation',
        })

        expect(context.timetableOptionsUnavailable).toBe(false)
        expect(context.timetableOptionsButtonUnavailable).toBe(true)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'no-saturday')).toBe(true)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'starts-from-period-10')).toBe(true)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'max-free-days')).toBe(true)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'no-distance-learning')).toBe(true)

        context.timetableCalculationResult.quality_counters[0].count = 2

        expect(context.timetableOptionsButtonUnavailable).toBe(false)
    })

    it('rolls back max free days when the recalculated timetable contains a regular overlap', async () => {
        const previousCalculationResult = {
            full_green_timetable_count: 4,
            green_timetable_count: 0,
            conflict_timetable_count: 0,
            selected_timetable: {
                number: 4,
                type: 'full_green',
                slots: {
                    '1-10': {
                        code: 'S2',
                        sourceLabel: 'SPA2-3C-WIR',
                        courseGroup: {
                            weekday: 1,
                            hour: 10,
                        },
                    },
                },
            },
        }
        const overlappingCalculationResult = {
            full_green_timetable_count: 2,
            green_timetable_count: 0,
            conflict_timetable_count: 0,
            selected_quality_criteria_count: 2,
            selected_timetable: {
                number: 1,
                type: 'full_green',
                slots: {
                    '2-13': {
                        code: 'CH2',
                        sourceLabel: 'CH2-5K-PLA',
                        dateRangeLabel: '24.02.-30.6.',
                        courseGroup: {
                            weekday: 2,
                            hour: 13,
                        },
                        sameSlotEntries: [
                            {
                                code: 'M5',
                                sourceLabel: 'M5-5K-DOM',
                                dateRangeLabel: '17.02.-7.7.',
                                courseGroup: {
                                    weekday: 2,
                                    hour: 13,
                                },
                            },
                        ],
                    },
                },
            },
        }
        const context = timetableV2Context({
            calculateTimetables: vi.fn(async () => {
                context.timetableCalculationResult = overlappingCalculationResult

                return overlappingCalculationResult
            }),
            timetableCalculationResult: previousCalculationResult,
            timetableCalculationSelectedNumber: 4,
            timetableMaxFreeDaysDraftSelected: true,
            timetableOptionsCardVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        const result = await TimetableV2.methods.applyTimetableOptions.call(context)

        expect(result).toBeNull()
        expect(context.timetableMaxFreeDaysSelected).toBe(false)
        expect(context.timetableMaxFreeDaysDraftSelected).toBe(false)
        expect(context.timetableCalculationResult).toBe(previousCalculationResult)
        expect(context.timetableCalculationSelectedNumber).toBe(4)
        expect(context.timetableCalculationError).toBe('Die Option wurde nicht übernommen, weil sie zu Überschneidungen führt.')
        expect(context.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Options: expect.objectContaining({
                maxFreeDays: false,
            }),
        }))
    })

    it('shows selected timetable options below the selected courses card', () => {
        expect(timetableV2Context().selectedTimetableOptionItems).toEqual([])
        expect(timetableV2Context().selectedTimetableOptionsSummaryCardVisible).toBe(false)
        expect(timetableV2Context({ timetableCalculationVisible: true }).selectedTimetableOptionsSummaryCardVisible).toBe(true)

        const context = timetableV2Context({
            timetableCalculationResult: {
                quality_counters: [
                    {
                        key: 'free_days',
                        count: 5,
                        best_value: 3,
                    },
                ],
            },
            timetableMaxFreeDaysDraftSelected: true,
            timetableMaxFreeDaysSelected: true,
            timetableNoDistanceLearningDraftSelected: true,
            timetableNoDistanceLearningSelected: true,
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: true,
            timetableStartsFromPeriod10DraftSelected: true,
            timetableStartsFromPeriod10Selected: true,
        })

        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'no-saturday',
                label: 'Kein Samstag',
            },
            {
                key: 'starts-from-period-10',
                label: 'Erst ab 10. Stunde',
            },
            {
                key: 'no-distance-learning',
                label: 'Kein Fernunterricht',
            },
            {
                key: 'max-free-days',
                label: 'Max 3 freie Tage',
            },
        ])
    })

    it('shows selected timetable options from the adopted timetable snapshot', () => {
        const context = timetableV2Context({
            adoptedTimetableCalculationOptionsSnapshot: {
                maxFreeDays: true,
                noDistanceLearning: false,
                noSaturday: true,
                startsFromPeriod10: true,
            },
            timetableMaxFreeDaysDraftSelected: false,
            timetableMaxFreeDaysSelected: false,
            timetableNoDistanceLearningDraftSelected: false,
            timetableNoDistanceLearningSelected: false,
            timetableNoSaturdayDraftSelected: false,
            timetableNoSaturdaySelected: false,
            timetableStartsFromPeriod10DraftSelected: false,
            timetableStartsFromPeriod10Selected: false,
            timetableV2Step: 'timetable-adoption',
        })

        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'no-saturday',
                label: 'Kein Samstag',
            },
            {
                key: 'starts-from-period-10',
                label: 'Erst ab 10. Stunde',
            },
            {
                key: 'max-free-days',
                label: 'Max freie Tage',
            },
        ])
        expect(context.selectedTimetableOptionsSummaryCardVisible).toBe(true)
    })

    it('disables selected course and option chips while more courses or options are open', () => {
        const moreCoursesContext = timetableV2Context({
            moreCoursesVisible: true,
            timetableCalculationVisible: true,
        })
        const optionsContext = timetableV2Context({
            timetableCalculationVisible: true,
            timetableOptionsCardVisible: true,
        })

        expect(moreCoursesContext.selectedTimetableSummaryChipsDisabled).toBe(true)
        expect(moreCoursesContext.selectedCourseItemsDisabled).toBe(true)
        expect(moreCoursesContext.selectedCourseItemsDeletable).toBe(false)
        expect(moreCoursesContext.selectedTimetableOptionItemsDeletable).toBe(false)
        expect(optionsContext.selectedTimetableSummaryChipsDisabled).toBe(true)
        expect(optionsContext.selectedCourseItemsDisabled).toBe(true)
        expect(optionsContext.selectedCourseItemsDeletable).toBe(false)
        expect(optionsContext.selectedTimetableOptionItemsDeletable).toBe(false)
    })

    it('ignores selected course chip actions while the more courses picker is open', () => {
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            moreCoursesVisible: true,
            moreCoursesCardVisible: true,
            saveStoredTimetableState,
            selectedReviewCourseKey: 'planned:M1',
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, key: 'D1', label: 'D1' },
                { code: 'M1', hours: 4, key: 'M1', label: 'M1' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'planned:D1': true,
                        'planned:M1': true,
                    },
                },
                transferredStudentContext: null,
            },
            timetableV2Step: 'timetable-calculation',
        })
        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(
            context,
            context.storedPlannedCourseItems[0],
            'planned',
        )

        expect(context.selectedCourseItemsDisabled).toBe(true)
        expect(context.selectedCourseItemsDeletable).toBe(false)
        expect(TimetableV2.methods.selectedCourseItemPassive.call(context)).toBe(true)

        TimetableV2.methods.selectReviewCourse.call(context, selectedCourse)
        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)

        expect(context.selectedReviewCourseKey).toBe('planned:M1')
        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(true)
        expect(saveStoredTimetableState).not.toHaveBeenCalled()
    })

    it('locks non-confirmation actions while changes are waiting for apply or cancel', () => {
        const pendingRemovedCourseContext = timetableV2Context({
            moreCoursesVisible: true,
            moreCoursesCardVisible: false,
            timetableCalculationVisible: true,
        })
        const selectingMoreCoursesContext = timetableV2Context({
            moreCoursesVisible: true,
            moreCoursesCardVisible: true,
            timetableCalculationVisible: true,
        })
        const optionsContext = timetableV2Context({
            timetableCalculationVisible: true,
            timetableOptionsCardVisible: true,
        })

        expect(pendingRemovedCourseContext.timetablePendingActionConfirmationVisible).toBe(true)
        expect(selectingMoreCoursesContext.timetablePendingActionConfirmationVisible).toBe(true)
        expect(optionsContext.timetablePendingActionConfirmationVisible).toBe(true)
        expect(timetableV2Context({ timetableCalculationVisible: true }).timetablePendingActionConfirmationVisible).toBe(false)
    })

    it('does not stage selected option removal while more courses or options are open', () => {
        const context = timetableV2Context({
            moreCoursesVisible: true,
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: true,
        })

        TimetableV2.methods.removeSelectedTimetableOption.call(context, {
            key: 'no-saturday',
        })

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'no-saturday',
                label: 'Kein Samstag',
            },
        ])
    })

    it('stages selected timetable option removal through the pending action row', () => {
        const context = timetableV2Context({
            timetableMaxFreeDaysDraftSelected: true,
            timetableMaxFreeDaysSelected: true,
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: true,
        })

        TimetableV2.methods.removeSelectedTimetableOption.call(context, {
            key: 'max-free-days',
        })

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(context.timetableNoSaturdaySelected).toBe(true)
        expect(context.timetableMaxFreeDaysDraftSelected).toBe(false)
        expect(context.timetableMaxFreeDaysSelected).toBe(true)
        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'no-saturday',
                label: 'Kein Samstag',
            },
        ])
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

    it('allows resetting when max free days is already active', () => {
        const context = timetableV2Context({
            initialTimetableCalculationMaxFreeDaysSelected: true,
            initialTimetableCalculationSelectedNumber: 1,
            initialTimetableCalculationSelection: {},
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                timetableV2Options: {
                    maxFreeDays: true,
                    noDistanceLearning: false,
                    noSaturday: false,
                    startsFromPeriod10: false,
                },
                transferredStudentContext: null,
            },
            timetableCalculationSelectedNumber: 1,
            timetableCalculationVisible: true,
            timetableMaxFreeDaysSelected: true,
        })

        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'max-free-days',
                label: 'Max freie Tage',
            },
        ])
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
                maxFreeDays: false,
                noDistanceLearning: false,
                noSaturday: false,
                startsFromPeriod10: false,
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

    it('removes active timetable options when resetting calculation changes', () => {
        let context: ReturnType<typeof timetableV2Context>
        const calculateTimetables = vi.fn()
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables,
            initialTimetableCalculationMaxFreeDaysSelected: true,
            initialTimetableCalculationNoSaturdaySelected: true,
            initialTimetableCalculationSelectedNumber: 1,
            initialTimetableCalculationSelection: {},
            saveStoredTimetableState,
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                timetableV2Options: {
                    maxFreeDays: true,
                    noDistanceLearning: true,
                    noSaturday: true,
                    startsFromPeriod10: true,
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationSelectedNumber: 1,
            timetableCalculationVisible: true,
            timetableMaxFreeDaysDraftSelected: true,
            timetableMaxFreeDaysSelected: true,
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: true,
            timetableStartsFromPeriod10DraftSelected: true,
            timetableStartsFromPeriod10Selected: true,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.resetTimetableCalculationChanges.call(context)

        expect(context.timetableMaxFreeDaysSelected).toBe(false)
        expect(context.timetableMaxFreeDaysDraftSelected).toBe(false)
        expect(context.timetableNoSaturdaySelected).toBe(false)
        expect(context.timetableNoSaturdayDraftSelected).toBe(false)
        expect(context.timetableStartsFromPeriod10Selected).toBe(false)
        expect(context.timetableStartsFromPeriod10DraftSelected).toBe(false)
        expect(context.timetableCalculationResetAvailable).toBe(false)
        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Options: {
                maxFreeDays: false,
                noDistanceLearning: false,
                noSaturday: false,
                startsFromPeriod10: false,
            },
        }))
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('renders more course options as adoption-style course cards', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')

        expect(source).not.toContain('<v-checkbox-btn')
        expect(source).toMatch(/<v-card[\s\S]*v-for="course in activeMoreCoursesCategoryCard\.items"[\s\S]*class="students-timetable-v2-more-courses-card__course"/u)
        expect(source).toContain('`students-timetable-v2-more-courses-card__course--${moreCourseChipColor(course)}`')
        expect(source).toContain("'students-timetable-v2-more-courses-card__course--active': selectedMoreCourseItem?.selectionKey === course.selectionKey")
        expect(source).toContain(':disabled="moreCourseOpenDisabled(course)"')
        expect(source).toContain("'students-timetable-v2-more-courses-card__course--disabled': moreCourseOpenDisabled(course)")
        expect(source).not.toContain("'students-timetable-v2-selected-courses-card__course--offered-deselected': moreCourseOfferedCourseItemsAllDeselected(course)")
        expect(source).toContain("'students-timetable-v2-more-courses-card__course--unavailable': moreCourseUnavailable(course)")
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__list \{[\s\S]*grid-template-columns: repeat\(auto-fit, minmax\(116px, 1fr\)\);/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course \{[\s\S]*min-height: 64px;[\s\S]*background: #bbf7d0;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course--disabled \{[\s\S]*opacity: 0\.48;[\s\S]*transform: none !important;/u)
        expect(source).not.toContain('background: rgba(241, 245, 249, 0.78) !important;')
        expect(source).not.toContain('filter: saturate(0.65);')
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course--success\.students-timetable-v2-more-courses-card__course--disabled:hover,[\s\S]*background: #bbf7d0;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course--warning\.students-timetable-v2-more-courses-card__course--disabled:hover,[\s\S]*background: #fde68a;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course--error\.students-timetable-v2-more-courses-card__course--disabled:hover,[\s\S]*background: #fecaca;/u)
        expect(source).toContain('.students-timetable-v2-more-courses-card__course--warning:hover')
        expect(source).toContain('.students-timetable-v2-more-courses-card__course--error:hover')
        expect(source).toMatch(/@click="toggleMoreCourseOffers\(course\)"/u)
    })

    it('keeps the open more-course card color stable while offer availability updates', () => {
        const moreCourse = {
            label: 'E2',
            selectionKey: 'missing:E2',
            offeredCourses: [
                { selectionKey: 'missing:E2::offer-a' },
                { selectionKey: 'missing:E2::offer-b' },
            ],
        }
        const context = timetableV2Context({
            offeredCourseItemsForSelectedCourse: (course) => course.offeredCourses || [],
            selectedMoreCourseKey: moreCourse.selectionKey,
            storedMissingCourseCardItems: [moreCourse],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    moreOfferedCourseSelections: {
                        'missing:E2::offer-a': true,
                        'missing:E2::offer-b': true,
                    },
                },
                transferredStudentContext: null,
            },
        })

        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: true,
            [`${moreCourse.selectionKey}::offer::missing:E2::offer-a`]: true,
            [`${moreCourse.selectionKey}::offer::missing:E2::offer-b`]: false,
        }

        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('warning')

        context.selectedMoreCourseKey = ''

        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('warning')
    })

    it('requires offer availability before the more courses availability batch is complete', () => {
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'BU2-4A-KOW', course: 'BU2', hour: 1, title: 'BU2', weekday: 1 },
                { class_name: 'BU2-2S-WIN', course: 'BU2', hour: 2, title: 'BU2', weekday: 2 },
            ],
            storedMissingCourseCardItems: [
                { code: 'BU2', hours: 4, key: 'BU2', label: 'BU2' },
            ],
        })
        const moreCourse = context.moreCoursesCardItems[0]
        const availabilityCandidates = TimetableV2.methods.moreCourseAvailabilityPayloadCandidates.call(context, moreCourse, { includeOffers: true })

        context.moreCourseAvailabilitySignature = TimetableV2.methods.moreCourseAvailabilityCurrentSignature.call(context)
        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: true,
        }

        expect(TimetableV2.methods.moreCourseAvailabilityComplete.call(context)).toBe(false)

        context.moreCourseAvailabilityByKey = Object.fromEntries(
            availabilityCandidates.map((course) => [course.availability_key, true])
        )

        expect(TimetableV2.methods.moreCourseAvailabilityComplete.call(context)).toBe(true)
    })

    it('opens more course offers without refreshing availability for that course', () => {
        const requestMoreCourseAvailability = vi.fn()
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'BU2-4A-KOW', course: 'BU2', hour: 1, title: 'BU2', weekday: 1 },
            ],
            moreCoursesCardVisible: true,
            moreCoursesVisible: true,
            requestMoreCourseAvailability,
            storedMissingCourseCardItems: [
                { code: 'BU2', hours: 4, key: 'BU2', label: 'BU2' },
            ],
        })
        const moreCourse = context.moreCoursesCardItems[0]

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        expect(context.selectedMoreCourseKey).toBe(moreCourse.selectionKey)
        expect(requestMoreCourseAvailability).not.toHaveBeenCalled()
    })

    it('keeps selected more course offers open when the parent availability key becomes unavailable', () => {
        const context = timetableV2Context({
            moreCoursesVisible: true,
            selectedMoreCourseKey: 'missing:BU2',
        })

        TimetableV2.methods.setMoreCourseAvailability.call(context, 'missing:BU2', false)

        expect(context.selectedMoreCourseKey).toBe('missing:BU2')
    })

    it('shows five category cards in the more courses card', () => {
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'additional:GS1': true,
                'planned:GW1': false,
            },
            storedAdditionalCourseItems: [
                { code: 'GS1', hours: 4, label: 'GS1' },
            ],
            storedCompletedCourseItems: [
                { code: 'BU1', grade: '1', label: 'BU1' },
            ],
            storedMissingCourseCardItems: [
                { code: 'M1', hours: 3, label: 'M1' },
            ],
            storedPlannedCourseItems: [
                { code: 'GW1', hours: 4, label: 'GW1' },
            ],
            storedSemesterCourseItems: [
                { code: 'D1', hours: 3, label: 'D1' },
            ],
        })

        expect(context.moreCoursesCategoryCards.map((card) => ({
            itemLabels: card.items.map((course) => course.label),
            key: card.key,
            title: card.title,
        }))).toEqual([
            { itemLabels: ['BU1'], key: 'completed', title: 'Abgeschlossene' },
            { itemLabels: ['M1'], key: 'missing', title: 'Negative' },
            { itemLabels: ['GWB1'], key: 'planned', title: 'Frühere' },
            { itemLabels: [], key: 'semester', title: 'Aktuelle' },
            { itemLabels: [], key: 'additional', title: 'Zusätzliche' },
        ])
    })

    it('renders apply and close buttons for offered courses in the more courses card', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')

        expect(source).toMatch(/class="students-timetable-v2-offered-courses-card__actions"[\s\S]*append-icon="mdi-check"[\s\S]*:disabled="!moreCoursesSelectionChanged \|\| timetableCalculationLoading"[\s\S]*@click\.stop="applyMoreCoursesSelection">\s+Anwenden[\s\S]*@click\.stop="closeMoreCourseOffers">\s+Schließen/u)
        expect(source).toMatch(/class="students-timetable-v2-offered-courses-card__actions"[\s\S]*size="small"[\s\S]*color="success"[\s\S]*variant="tonal"[\s\S]*append-icon="mdi-check"/u)
        expect(source).toContain('prepend-icon="mdi-close"')
        expect(source).toMatch(/prepend-icon="mdi-close"[\s\S]*color="error"[\s\S]*aria-label="Schließen"/u)
        expect(source).toContain('aria-label="Schließen"')
        expect(source).toMatch(/@click\.stop="closeMoreCourseOffers">\s+Schließen/u)
        expect(source).toMatch(/@click\.stop="closeMoreCourseOffers"/u)
        expect(source).toContain("'students-timetable-v2-offered-courses-card__item--deselected': !moreOfferedCourseSelected(course) && !moreCourseOfferUnavailable(course)")
        expect(source).toContain("'students-timetable-v2-offered-courses-card__item--available': !moreCourseOfferUnavailable(course)")
        expect(source).toContain("'students-timetable-v2-offered-courses-card__item--unavailable': moreCourseOfferUnavailable(course)")
        expect(source).toContain(':aria-disabled="moreCourseOfferDisabled(course) ? \'true\' : \'false\'"')
        expect(source).toContain('@click="toggleMoreOfferedCourseItem(course, selectedMoreCourseItem)"')
    })

    it('shows five adopted course option cards without details', () => {
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'missing:BU1': false,
                'planned:GW1': false,
            },
            storedAdditionalCourseItems: [
                { code: 'GS1', hours: 4, label: 'GS1' },
            ],
            storedCompletedCourseItems: [
                { code: 'BU0', grade: '1', label: 'BU0' },
            ],
            storedMissingCourseCardItems: [
                { code: 'BU1', hours: 3, label: 'BU1' },
            ],
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, label: 'D1' },
                { code: 'GW1', hours: 4, label: 'GW1' },
            ],
        })

        expect(context.moreAdoptedCourseCards.map((card) => ({
            key: card.key,
            title: card.title,
        }))).toEqual([
            { key: 'completed', title: 'Abgeschlossene' },
            { key: 'missing', title: 'Negative' },
            { key: 'planned', title: 'Vorgesehene' },
            { key: 'additional', title: 'Zusätzliche' },
            { key: 'more', title: 'Weitere' },
        ])
    })

    it('does not open adopted course category cards without available courses', () => {
        const context = timetableV2Context({
            activeMoreAdoptedCourseCardKey: '',
            storedMissingCourseCardItems: [],
            storedPlannedCourseItems: [],
            storedAdditionalCourseItems: [],
            timetableV2Step: 'timetable-adoption',
        })
        const [missingCard] = context.moreAdoptedCourseCards

        expect(TimetableV2.methods.moreAdoptedCourseCardDisabled.call(context, missingCard)).toBe(true)

        TimetableV2.methods.openMoreAdoptedCourseCard.call(context, missingCard)

        expect(context.activeMoreAdoptedCourseCardKey).toBe('')
    })

    it('opens adopted course categories with courses that are not in the timetable', () => {
        const context = timetableV2Context({
            activeMoreAdoptedCourseCardKey: '',
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '1-1': {
                            code: 'BU2',
                            courseGroup: { course: 'BU2', hour: 1, weekday: 1 },
                        },
                    },
                },
            },
            courseGroups: [
                { class_name: 'BU1-1A-FUCH', course: 'BU1', hour: 1, title: 'BU1', weekday: 1 },
                { class_name: 'BU2-1A-FUCH', course: 'BU2', hour: 2, title: 'BU2', weekday: 2 },
                { class_name: 'PH1-1A-FUCH', course: 'PH1', hour: 3, title: 'PH1', weekday: 3 },
            ],
            requestMoreCourseAvailability: vi.fn(),
            storedMissingCourseCardItems: [
                { code: 'BU1', hours: 3, label: 'BU1' },
                { code: 'BU2', hours: 3, label: 'BU2' },
                { code: 'PH1', hours: 3, label: 'PH1' },
            ],
            timetableV2Step: 'timetable-adoption',
        })

        TimetableV2.methods.openMoreAdoptedCourseCard.call(
            context,
            context.moreAdoptedCourseCards.find((card) => card.key === 'missing'),
        )

        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module: Negative')
        expect(context.activeMoreAdoptedCourseCard?.title).toBe('Negative')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).toEqual(['BU1', 'PH1'])
        expect(context.moreAdoptedCourseItems.map((course) => course.label)).toEqual(['BU1', 'PH1'])
        expect(context.requestMoreCourseAvailability).not.toHaveBeenCalled()

        TimetableV2.methods.closeMoreAdoptedCourseCard.call(context)

        expect(context.activeMoreAdoptedCourseCard).toBeNull()
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module')
    })

    it('opens adopted completed courses that are not in the timetable', () => {
        const context = timetableV2Context({
            activeMoreAdoptedCourseCardKey: '',
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '1-1': {
                            code: 'BU2',
                            courseGroup: { course: 'BU2', hour: 1, weekday: 1 },
                        },
                    },
                },
            },
            storedCompletedCourseItems: [
                { code: 'BU0', grade: '1', label: 'BU0' },
                { code: 'BU2', grade: '1', label: 'BU2' },
            ],
            timetableV2Step: 'timetable-adoption',
        })
        const completedCard = context.moreAdoptedCourseCards.find((card) => card.key === 'completed')

        TimetableV2.methods.openMoreAdoptedCourseCard.call(context, completedCard)

        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module: Abgeschlossene')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).toEqual(['BU0'])
        expect(context.moreAdoptedCourseItems.map((course) => course.label)).toEqual(['BU0'])
        expect(TimetableV2.methods.moreAdoptedCourseCardDisabled.call(context, completedCard)).toBe(false)
    })

    it('hides planned adopted courses without offered courses', () => {
        const context = timetableV2Context({
            activeMoreAdoptedCourseCardKey: '',
            courseGroups: [
                { class_name: 'CH2-4F-KOW', course: 'CH2', hour: 2, title: 'CH2', weekday: 2 },
            ],
            storedPlannedCourseItems: [
                { code: 'CH2', hours: 3, label: 'CH2' },
                { code: 'RIS3', hours: 2, label: 'RIS3' },
            ],
            timetableV2Step: 'timetable-adoption',
        })
        const plannedCard = context.moreAdoptedCourseCards.find((card) => card.key === 'planned')

        TimetableV2.methods.openMoreAdoptedCourseCard.call(context, plannedCard)

        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module: Vorgesehene')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).toEqual(['CH2'])
        expect(context.moreAdoptedCourseItems.map((course) => course.label)).toEqual(['CH2'])
        expect(TimetableV2.methods.moreAdoptedCourseCardDisabled.call(context, plannedCard)).toBe(false)

        context.courseGroups = []

        expect(context.moreAdoptedCourseCandidateItems).toEqual([])
        expect(TimetableV2.methods.moreAdoptedCourseCardDisabled.call(context, plannedCard)).toBe(true)
    })

    it('lists only uncategorized existing courses in the adopted more category', () => {
        const context = timetableV2Context({
            activeMoreAdoptedCourseCardKey: 'more',
            courseGroups: [
                { class_name: 'BU1-1A-FUCH', course: 'BU1', hour: 1, title: 'BU1', weekday: 1 },
                { class_name: 'D1-1A-FUCH', course: 'D1', hour: 2, title: 'D1', weekday: 1 },
                { class_name: 'GS1-1A-FUCH', course: 'GS1', hour: 3, title: 'GS1', weekday: 1 },
                { class_name: 'INF2-1A-FUCH', course: 'INF2', hour: 4, title: 'INF2', weekday: 1 },
                { class_name: 'INF3-1A-FUCH', course: 'INF3', hour: 5, title: 'INF3', weekday: 1 },
                { class_name: 'L1-2A-UNT', course: 'L', hour: 6, module_code: 'L1', title: 'L1', weekday: 1 },
                { class_name: 'L2-3C-WE', course: 'L', hour: 7, module_code: 'L2', title: 'L2', weekday: 1 },
                { class_name: 'L-UNT', course: 'L', hour: 8, title: 'L', weekday: 1 },
                { class_name: 'CH1-4F-KOW', course: 'CH', hour: 9, module_code: 'CH1', title: 'CH1', weekday: 1 },
                { class_name: 'CH2-5C-KOW', course: 'CH', hour: 10, module_code: 'CH2', title: 'CH2', weekday: 1 },
                { class_name: 'CH-KOW', course: 'CH', hour: 11, title: 'CH', weekday: 1 },
                { class_name: 'M1-2A-UNT', course: 'M', hour: 12, module_code: 'M1', title: 'M1', weekday: 1 },
                { class_name: 'M2-3C-WE', course: 'M', hour: 13, module_code: 'M2', title: 'M2', weekday: 1 },
                { class_name: 'PH2-1A-FUCH', course: 'PH2', hour: 5, title: 'PH2', weekday: 1 },
                { class_name: 'PH-1A-REN', course: 'PH', display_label: 'PH - REN', hour: 6, title: 'PH REN', weekday: 1 },
                { class_name: 'ÖKO1-1A-FUCH', course: 'ÖKO1', hour: 7, title: 'ÖKO1', weekday: 1 },
                { class_name: 'ÖKO2-1A-FUCH', course: 'ÖKO2', hour: 8, title: 'ÖKO2', weekday: 1 },
                { class_name: 'ÖKO3-1A-FUCH', course: 'ÖKO3', hour: 9, title: 'ÖKO3', weekday: 1 },
                { class_name: 'OKON-1A-FUCH', course: 'OKON', hour: 10, title: 'OKON', weekday: 1 },
                { class_name: 'S1-1A-FUCH', course: 'S1', hour: 11, title: 'S1', weekday: 1 },
                { class_name: 'S2-1A-FUCH', course: 'S2', hour: 12, title: 'S2', weekday: 1 },
                { class_name: 'SPA-1A-FUCH', course: 'SPA', hour: 13, title: 'SPA', weekday: 1 },
            ],
            storedAdditionalCourseItems: [
                { code: 'GS1', hours: 4, label: 'GS1' },
            ],
            storedMissingCourseCardItems: [
                { code: 'BU1', hours: 3, label: 'BU1' },
            ],
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, label: 'D1' },
            ],
            timetableV2Step: 'timetable-adoption',
        })

        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module: Weitere')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).toEqual([
            'CH1',
            'CH2',
            'INF2',
            'INF3',
            'L1',
            'L2',
            'M1',
            'M2',
            'ÖKO1',
            'ÖKO2',
            'ÖKO3',
            'PH',
            'PH2',
            'SPA1',
            'SPA2',
        ])
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).not.toContain('REN')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).not.toContain('CH')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).not.toContain('L')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).not.toContain('M')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).not.toContain('OKON')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).not.toContain('SPA')
        expect(context.moreAdoptedCourseItems.map((course) => ({
            label: course.label,
            meta: course.meta,
        }))).toEqual([
            { label: 'CH', meta: '2 Module' },
            { label: 'INF', meta: '2 Module' },
            { label: 'L', meta: '2 Module' },
            { label: 'M', meta: '2 Module' },
            { label: 'ÖKO', meta: '3 Module' },
            { label: 'PH', meta: '2 Module' },
            { label: 'SPA', meta: '2 Module' },
        ])
        expect(context.moreAdoptedCourseItems.map((course) => course.courseGroup)).toEqual([
            'more-base',
            'more-base',
            'more-base',
            'more-base',
            'more-base',
            'more-base',
            'more-base',
        ])

        TimetableV2.methods.openMoreAdoptedCourseItem.call(
            context,
            context.moreAdoptedCourseItems.find((course) => course.label === 'INF'),
        )

        expect(context.activeMoreExistingCourseBaseKey).toBe('INF')
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module: Weitere: INF')
        expect(context.moreAdoptedCourseItems.map((course) => course.label)).toEqual(['INF2', 'INF3'])
    })

    it('drills from adopted more top-level courses to concrete courses and then offers', () => {
        const context = timetableV2Context({
            activeMoreAdoptedCourseCardKey: 'more',
            courseGroups: [
                { class_name: 'INF2-1A-FUCH', course: 'INF2', hour: 1, title: 'INF2', weekday: 1 },
                { class_name: 'INF2-1B-MAIR', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
                { class_name: 'INF3-1A-FUCH', course: 'INF3', hour: 3, title: 'INF3', weekday: 3 },
            ],
            timetableV2Step: 'timetable-adoption',
        })
        const [topLevelCourse] = context.moreAdoptedCourseItems

        expect(topLevelCourse).toMatchObject({
            isTopLevelCourseGroup: true,
            label: 'INF',
            meta: '2 Module',
        })

        TimetableV2.methods.openMoreAdoptedCourseItem.call(context, topLevelCourse)

        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module: Weitere: INF')
        expect(context.moreAdoptedCourseItems.map((course) => course.label)).toEqual(['INF2', 'INF3'])

        TimetableV2.methods.openMoreAdoptedCourseItem.call(context, context.moreAdoptedCourseItems[0])

        expect(context.selectedMoreAdoptedCourseItem?.label).toBe('INF2')
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module: Weitere: INF: INF2')
        expect(context.selectedMoreAdoptedCourseOfferedCourseItems.map((offer) => offer.name)).toEqual([
            'INF2 - 1A - FUCH',
            'INF2 - 1B - MAIR',
        ])

        TimetableV2.methods.closeMoreAdoptedCourseCard.call(context)

        expect(context.activeMoreAdoptedCourseCard?.title).toBe('Weitere')
        expect(context.activeMoreExistingCourseBaseKey).toBe('')
        expect(context.moreAdoptedCourseItems.map((course) => course.label)).toEqual(['INF'])
    })

    it('does not show aggregate language offers after selecting a concrete adopted language course', () => {
        const context = timetableV2Context({
            activeMoreAdoptedCourseCardKey: 'more',
            courseGroups: [
                { class_name: 'L1-2A-UNT', course: 'L', hour: 14, module_code: 'L1', title: 'L1', weekday: 1 },
                { class_name: 'L1-1RU+2F-UNT', course: 'L', hour: 14, module_code: 'L1', title: 'L1', weekday: 5 },
                { class_name: 'L2-3C-WE', course: 'L', hour: 10, module_code: 'L2', title: 'L2', weekday: 1 },
                { class_name: 'L3-4A-UNT', course: 'L', hour: 10, module_code: 'L3', title: 'L3', weekday: 1 },
                { class_name: 'L-WE', course: 'L', hour: 10, title: 'L', weekday: 5 },
            ],
            timetableV2Step: 'timetable-adoption',
        })

        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).toEqual(['L1', 'L2', 'L3'])
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).not.toContain('L')

        TimetableV2.methods.openMoreAdoptedCourseItem.call(context, context.moreAdoptedCourseItems[0])

        expect(context.moreAdoptedCourseItems.map((course) => course.label)).toEqual(['L1', 'L2', 'L3'])

        TimetableV2.methods.openMoreAdoptedCourseItem.call(context, context.moreAdoptedCourseItems[0])

        expect(context.selectedMoreAdoptedCourseItem?.label).toBe('L1')
        expect(context.selectedMoreAdoptedCourseOfferedCourseItems.map((offer) => offer.code)).toEqual(['L1', 'L1'])
        expect(context.selectedMoreAdoptedCourseOfferedCourseItems.map((offer) => offer.name)).toEqual([
            'L1 - 2A - UNT',
            'L1 - 1RU+2F - UNT',
        ])
        expect(context.selectedMoreAdoptedCourseOfferedCourseItems.map((offer) => offer.name).join('|')).not.toContain('L2')
        expect(context.selectedMoreAdoptedCourseOfferedCourseItems.map((offer) => offer.name).join('|')).not.toContain('L3')
    })

    it('shows selected course hours instead of grades in the course chip meta', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.selectedCourseListItem.call(context, {
            code: 'BU1',
            hours: 4,
            hoursMeta: '4 Std.',
            label: 'BU1',
            meta: '5',
        }, 'missing')).toMatchObject({
            label: 'BU1',
            meta: '4 Std.',
        })
    })

    it('shows all offered courses after selecting an adopted course candidate', () => {
        const context = timetableV2Context({
            activeMoreAdoptedCourseCardKey: 'missing',
            courseGroups: [
                { class_name: 'BU1-1A-FUCH', course: 'BU1', hour: 1, title: 'BU1', weekday: 1 },
                { class_name: 'BU1-1B-MAIR', course: 'BU1', hour: 2, title: 'BU1', weekday: 2 },
            ],
            saveStoredTimetableState: vi.fn(),
            storedMissingCourseCardItems: [
                { code: 'BU1', hours: 3, label: 'BU1' },
            ],
            timetableV2Step: 'timetable-adoption',
        })
        const [course] = context.moreAdoptedCourseItems

        TimetableV2.methods.openMoreAdoptedCourseOffers.call(context, course)

        expect(context.selectedMoreAdoptedCourseItem?.label).toBe('BU1')
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module: Negative: BU1')
        expect(context.selectedMoreAdoptedCourseOfferedCourseItems.map((offer) => offer.name)).toEqual([
            'BU1 - 1A - FUCH',
            'BU1 - 1B - MAIR',
        ])
        expect(context.saveStoredTimetableState).not.toHaveBeenCalled()

        TimetableV2.methods.openMoreAdoptedCourseOffers.call(context, course)

        expect(context.selectedMoreAdoptedCourseItem).toBeNull()
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module: Negative')
    })

    it('marks adopted offered courses red when they collide with the current timetable', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const context = timetableV2Context({
            activeMoreAdoptedCourseCardKey: 'missing',
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '1-1': {
                            code: 'M1',
                            courseGroup: {
                                hour: 1,
                                weekday: 1,
                            },
                            sourceLabel: 'M1-A',
                        },
                    },
                },
            },
            courseGroups: [
                { class_name: 'BU1-1A-FUCH', course: 'BU1', hour: 1, title: 'BU1', weekday: 1 },
                { class_name: 'BU1-1B-MAIR', course: 'BU1', hour: 2, title: 'BU1', weekday: 2 },
            ],
            storedMissingCourseCardItems: [
                { code: 'BU1', hours: 3, label: 'BU1' },
            ],
            timetableV2Step: 'timetable-adoption',
        })
        const [course] = context.moreAdoptedCourseItems

        TimetableV2.methods.openMoreAdoptedCourseOffers.call(context, course)

        expect(source).toContain(':color="moreAdoptedCourseColor(course)"')
        expect(source).toContain('`students-timetable-v2-more-adopted-courses-card__course--${moreAdoptedCourseColor(course)}`')
        expect(source).toContain('.students-timetable-v2-more-adopted-courses-card__course--warning:hover')
        expect(source).toContain('.students-timetable-v2-more-adopted-courses-card__course--error:hover')
        expect(source).toContain("'students-timetable-v2-offered-courses-card__item--available': !moreAdoptedCourseOfferCollides(course)")
        expect(source).toContain("'students-timetable-v2-offered-courses-card__item--colliding': moreAdoptedCourseOfferCollides(course)")
        expect(source).toContain(":title=\"moreAdoptedCourseOfferCollides(course) ? 'Überschneidet sich mit dem aktuellen Stundenplan.' : undefined\"")
        expect(source).toContain('.students-timetable-v2-offered-courses-card__item--available')
        expect(source).toContain('.students-timetable-v2-offered-courses-card__item--colliding')
        expect(TimetableV2.methods.moreAdoptedCourseOfferCollides.call(
            context,
            context.selectedMoreAdoptedCourseOfferedCourseItems[0],
        )).toBe(true)
        expect(TimetableV2.methods.moreAdoptedCourseOfferCollides.call(
            context,
            context.selectedMoreAdoptedCourseOfferedCourseItems[1],
        )).toBe(false)
        expect(TimetableV2.methods.moreAdoptedCourseColor.call(context, course)).toBe('warning')

        context.courseGroups = [
            { class_name: 'BU1-1A-FUCH', course: 'BU1', hour: 1, title: 'BU1', weekday: 1 },
            { class_name: 'BU1-1B-MAIR', course: 'BU1', hour: 1, title: 'BU1', weekday: 1 },
        ]

        expect(TimetableV2.methods.moreAdoptedCourseColor.call(context, course)).toBe('error')

        context.adoptedTimetableCalculationResult = {
            selected_timetable: {
                slots: {},
            },
        }

        expect(TimetableV2.methods.moreAdoptedCourseColor.call(context, course)).toBe('success')
    })

    it('inserts a selected adopted course offer into the adopted timetable', () => {
        const context = timetableV2Context({
            activeMoreAdoptedCourseCardKey: 'missing',
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '1-1': {
                            code: 'M1',
                            courseGroup: {
                                hour: 1,
                                weekday: 1,
                            },
                            sourceLabel: 'M1-A',
                        },
                    },
                },
            },
            adoptedTimetableSelectionSnapshot: {
                courseSelections: {
                    'missing:BU1': false,
                },
            },
            courseGroups: [
                { class_name: 'BU1-1A-FUCH', course: 'BU1', hour: 1, title: 'BU1', weekday: 1 },
            ],
            saveStoredTimetableState: vi.fn(),
            storedMissingCourseCardItems: [
                { code: 'BU1', hours: 3, label: 'BU1' },
            ],
            timetableV2Step: 'timetable-adoption',
        })
        const [course] = context.moreAdoptedCourseItems

        TimetableV2.methods.openMoreAdoptedCourseOffers.call(context, course)
        TimetableV2.methods.insertMoreAdoptedCourseOfferIntoTimetable.call(
            context,
            context.selectedMoreAdoptedCourseOfferedCourseItems[0],
        )

        expect(context.adoptedTimetableCalculationResult.selected_timetable.slots['1-1']).toEqual({
            code: 'M1',
            conflicts: [
                expect.objectContaining({
                    code: 'BU1',
                    courseGroup: expect.objectContaining({
                        class_name: 'BU1-1A-FUCH',
                        hour: 1,
                        weekday: 1,
                    }),
                    sourceLabel: 'BU1-1A-FUCH',
                }),
            ],
            courseGroup: {
                hour: 1,
                weekday: 1,
            },
            sourceLabel: 'M1-A',
        })
        expect(context.adoptedTimetableSelectionSnapshot.courseSelections).toEqual({
            'missing:BU1': true,
        })
        expect(context.activeMoreAdoptedCourseCardKey).toBe('')
        expect(context.activeMoreExistingCourseBaseKey).toBe('')
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Module')
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Adoption: expect.objectContaining({
                adoptedCalculationResult: expect.objectContaining({
                    selected_timetable: expect.objectContaining({
                        slots: expect.objectContaining({
                            '1-1': expect.objectContaining({
                                conflicts: expect.arrayContaining([
                                    expect.objectContaining({ code: 'BU1' }),
                                ]),
                            }),
                        }),
                    }),
                }),
            }),
        }))
    })

    it('shows additional courses as a default-off selectable course card', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const courseSelectionCardsSource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/CourseSelectionCards.vue', 'utf8')
        const additionalCourse = { code: 'INF2', hours: 4, label: 'INF2' }
        const saveStoredTimetableState = vi.fn()

        expect(source).toContain("items: this.storedAdditionalCourseItems.map((course) => this.courseSelectionCardItem(course, 'additional'))")
        expect(source).toContain("summaryChips: this.courseSelectionCardSummaryChips(this.storedAdditionalCourseSummary, 'success')")
        expect(source).toContain("toggleCourseSelectionCardItem(courseItem)")
        expect(courseSelectionCardsSource).toContain('v-for="course in card.items"')
        expect(courseSelectionCardsSource).toContain(':color="course.color"')
        expect(courseSelectionCardsSource).toContain(':variant="course.variant"')
        expect(courseSelectionCardsSource).toContain(':class="course.classes"')
        expect(courseSelectionCardsSource).toContain('@click="toggleCourse(course)"')
        expect(courseSelectionCardsSource).toContain('students-timetable-v2-completed-courses__item--toggle')
        expect(courseSelectionCardsSource).toMatch(/\.students-timetable-v2-completed-courses__item--additional \{[\s\S]*border-color: rgba\(22, 163, 74, 0\.18\);[\s\S]*background: rgba\(240, 253, 244, 0\.78\);/u)
        expect(courseSelectionCardsSource).toContain('v-if="courseSelectionDraftChanged"')
        expect(courseSelectionCardsSource).toContain('@click="$emit(\'apply-course-selections\', activeCourseSelections)"')
        expect(courseSelectionCardsSource).toContain('@click="resetDraftCourseSelections"')
        expect(courseSelectionCardsSource).toContain("emits: ['apply-course-selections', 'draft-change', 'draft-selections-change']")
        expect(courseSelectionCardsSource).toContain("this.$emit('draft-change', this.courseSelectionDraftChanged)")
        expect(courseSelectionCardsSource).toContain("this.$emit('draft-change', false)")
        expect(courseSelectionCardsSource).toContain("this.$emit('draft-selections-change', this.activeCourseSelections)")
        expect(courseSelectionCardsSource).toContain("this.$emit('draft-selections-change', null)")
        expect(courseSelectionCardsSource).toContain('students-timetable-v2-course-selection-draft-action')
        expect(courseSelectionCardsSource).toContain('Abbruch')

        const context = timetableV2Context({
            storedAdditionalCourseItems: [
                additionalCourse,
                { code: 'GS1', hours: 3, label: 'GS1' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
            saveStoredTimetableState,
        })
        const summary = TimetableV2.computed.storedAdditionalCourseSummary.call(context)

        expect(summary.countLabel).toBe('0 Module')
        expect(summary.hoursLabel).toBe('0 Std.')
        expect(TimetableV2.methods.courseItemSelected.call(context, additionalCourse, 'additional')).toBe(false)

        const selectedContext = timetableV2Context({
            courseSelectionOverrides: {
                'additional:INF2': true,
            },
            storedAdditionalCourseItems: [
                additionalCourse,
                { code: 'GS1', hours: 3, label: 'GS1' },
            ],
        })
        const selectedSummary = TimetableV2.computed.storedAdditionalCourseSummary.call(selectedContext)

        expect(selectedSummary.countLabel).toBe('1 Modul')
        expect(selectedSummary.hoursLabel).toBe('4 Std.')

        TimetableV2.methods.toggleCourseItem.call(context, additionalCourse, 'additional')

        expect(saveStoredTimetableState).not.toHaveBeenCalled()
        expect(context.draftCourseSelections).toEqual({
            'additional:INF2': true,
        })
        expect(context.courseSelectionDraftChanged).toBe(true)

        TimetableV2.methods.applyDraftCourseSelections.call(context)

        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Selection: {
                courseSelections: {
                    'additional:INF2': true,
                },
            },
        }))
    })

    it('shows completed courses as the first default-off selectable course card', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const courseSelectionCardsSource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/CourseSelectionCards.vue', 'utf8')
        const completedCardIndex = source.indexOf("title: 'Abgeschlossene Module'", source.indexOf('courseSelectionCards()'))
        const missingCardIndex = source.indexOf("title: 'Negative Module'", completedCardIndex)
        const completedCourse = { code: 'D1', hours: 4, label: 'D1', meta: '4' }
        const context = timetableV2Context({
            storedCompletedCourseItems: [completedCourse],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
        })

        expect(completedCardIndex).toBeGreaterThan(-1)
        expect(missingCardIndex).toBeGreaterThan(completedCardIndex)
        expect(source).toContain("items: this.storedCompletedCourseItems.map((course) => this.courseSelectionCardItem(course, 'completed'))")
        expect(source).toMatch(/key: 'completed',[\s\S]*mdColumns: 12,[\s\S]*title: 'Abgeschlossene Module'/u)
        expect(source).toContain("summaryChips: this.courseSelectionCardSummaryChips(this.storedCompletedCourseSummary, 'success')")
        expect(courseSelectionCardsSource).toContain('v-for="course in card.items"')
        expect(courseSelectionCardsSource).toContain(':md="card.mdColumns"')
        expect(courseSelectionCardsSource).toContain('mdColumns: card.mdColumns || this.courseCardMdColumns')
        expect(source).toContain('completedCourseItemMeta(course)')
        expect(TimetableV2.computed.storedCompletedCourseSummary.call(context).countLabel).toBe('0 Module')
        expect(TimetableV2.computed.storedCompletedCourseSummary.call(context).hoursLabel).toBe('0 Std.')
        expect(context.selectedCourseSummary.countLabel).toBe('0 Module')
        expect(context.selectedCourseSummary.hoursLabel).toBe('0 Std.')
        expect(TimetableV2.methods.completedCourseItemMeta.call(context, completedCourse)).toBe('4 Std.')
        expect(source).toContain('toggleCourseSelectionCardItem(courseItem)')
        expect(source).toContain("courseItemSelected(course, courseGroup)")
        expect(TimetableV2.methods.courseItemSelected.call(context, completedCourse, 'completed')).toBe(false)

        const selectedContext = timetableV2Context({
            courseSelectionOverrides: {
                'completed:D1': true,
            },
            storedCompletedCourseItems: [completedCourse],
        })

        expect(TimetableV2.computed.storedCompletedCourseSummary.call(selectedContext).countLabel).toBe('1 Modul')
        expect(TimetableV2.computed.storedCompletedCourseSummary.call(selectedContext).hoursLabel).toBe('4 Std.')
        expect(selectedContext.selectedCourseSummary.countLabel).toBe('1 Modul')
        expect(selectedContext.selectedCourseSummary.hoursLabel).toBe('4 Std.')
        expect(TimetableV2.methods.completedCourseItemMeta.call(selectedContext, completedCourse)).toBe('4 Std.')

        TimetableV2.methods.toggleCourseItem.call(context, completedCourse, 'completed')

        expect(context.saveStoredTimetableState).not.toHaveBeenCalled()
        expect(context.draftCourseSelections).toEqual({
            'completed:D1': true,
        })

        TimetableV2.methods.applyDraftCourseSelections.call(context)

        expect(context.saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Selection: {
                courseSelections: {
                    'completed:D1': true,
                },
            },
        }))
    })

    it('keeps completed course clicks selected after the parent refreshes card state', () => {
        const completedCourse = {
            color: 'success',
            courseGroup: 'completed',
            defaultSelected: false,
            hoursNumber: 4,
            key: 'completed-D1-4',
            label: 'D1',
            selectionKey: 'completed:D1',
            unavailable: false,
        }
        const context = {
            ...CourseSelectionCards.methods,
            $emit: vi.fn(),
            cards: [
                {
                    courseGroup: 'completed',
                    items: [completedCourse],
                    key: 'completed',
                    visible: true,
                },
            ],
            courseSelections: {},
            draftCourseSelections: null,
        }

        Object.defineProperties(context, {
            activeCourseSelections: {
                get() {
                    return CourseSelectionCards.computed.activeCourseSelections.call(context)
                },
            },
            cardRosterSignature: {
                get() {
                    return CourseSelectionCards.computed.cardRosterSignature.call(context)
                },
            },
            courseSelectionDraftChanged: {
                get() {
                    return CourseSelectionCards.computed.courseSelectionDraftChanged.call(context)
                },
            },
            visibleCards: {
                get() {
                    return CourseSelectionCards.computed.visibleCards.call(context)
                },
            },
        })

        const initialCardRosterSignature = context.cardRosterSignature

        CourseSelectionCards.methods.toggleCourse.call(context, completedCourse)

        expect(context.draftCourseSelections).toEqual({
            'completed:D1': true,
        })
        expect(context.$emit).toHaveBeenCalledWith('draft-selections-change', {
            'completed:D1': true,
        })

        context.cards = [
            {
                courseGroup: 'completed',
                items: [
                    {
                        ...completedCourse,
                        selected: true,
                    },
                ],
                key: 'completed',
                visible: true,
            },
        ]

        expect(context.cardRosterSignature).toBe(initialCardRosterSignature)
        expect(context.draftCourseSelections).toEqual({
            'completed:D1': true,
        })
    })

    it('clears stale course selection aliases when reselecting a default course', () => {
        const semesterCourse = {
            color: 'success',
            courseGroup: 'semester',
            defaultSelected: true,
            hoursNumber: 3,
            key: 'subject-row-m4',
            label: 'M4',
            selectionKey: 'semester:M4',
            selectionKeys: ['semester:M4', 'semester:subject-row-m4'],
            unavailable: false,
        }
        const context = {
            ...CourseSelectionCards.methods,
            $emit: vi.fn(),
            cards: [
                {
                    courseGroup: 'semester',
                    items: [semesterCourse],
                    key: 'semester',
                    visible: true,
                },
            ],
            courseSelections: {
                'semester:M4': true,
                'semester:subject-row-m4': false,
            },
            draftCourseSelections: null,
        }

        Object.defineProperties(context, {
            activeCourseSelections: {
                get() {
                    return CourseSelectionCards.computed.activeCourseSelections.call(context)
                },
            },
            courseSelectionDraftChanged: {
                get() {
                    return CourseSelectionCards.computed.courseSelectionDraftChanged.call(context)
                },
            },
            visibleCards: {
                get() {
                    return CourseSelectionCards.computed.visibleCards.call(context)
                },
            },
        })

        expect(CourseSelectionCards.methods.courseSelected.call(context, semesterCourse)).toBe(false)

        CourseSelectionCards.methods.toggleCourse.call(context, semesterCourse)

        expect(context.draftCourseSelections).toEqual({})
        expect(context.$emit).toHaveBeenCalledWith('draft-selections-change', {})
    })

    it('lists visited completed and failed courses alphabetically with grades and status colors', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                courses: {
                    completed: [
                        { code: 'D2', grade: '4' },
                        { code: 'BU1', grade: '3' },
                    ],
                    failed: [
                        { code: 'CH2', grade: 'N' },
                        { code: 'AM1', grade: '5' },
                    ],
                },
            },
        })

        expect(context.visitedCourseItems.map((course) => ({
            color: course.color,
            grade: course.grade,
            label: course.label,
            status: course.status,
        }))).toEqual([
            {
                color: 'error',
                grade: '5',
                label: 'AM1',
                status: 'failed',
            },
            {
                color: 'success',
                grade: '3',
                label: 'BU1',
                status: 'completed',
            },
            {
                color: 'error',
                grade: 'N',
                label: 'CH2',
                status: 'failed',
            },
            {
                color: 'success',
                grade: '4',
                label: 'D2',
                status: 'completed',
            },
        ])
    })

    it('uses religion subject row hours for generic completed religion modules', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                courses: {
                    completed: [
                        { code: 'R4', grade: '4' },
                        { code: 'R1', grade: '3' },
                    ],
                },
            },
            subjectRows: [
                { hours_per_week: 2, is_active: true, json_code: 'R1', json_subject: 'R/ET' },
                { hours_per_week: 2, is_active: true, json_code: 'R4', json_subject: 'R/ET' },
            ],
        })

        expect(context.storedCompletedCourseItems.map((course) => ({
            code: course.code,
            hours: course.hours,
            hoursMeta: course.hoursMeta,
        }))).toEqual([
            {
                code: 'R4',
                hours: 2,
                hoursMeta: '2 Std.',
            },
            {
                code: 'R1',
                hours: 2,
                hoursMeta: '2 Std.',
            },
        ])
        expect(TimetableV2.methods.completedCourseItemMeta.call(context, { code: 'R1', label: 'R1', meta: '3' })).toBe('2 Std.')
    })

    it('shows the imported student religion below the student title', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const context = timetableV2Context({
            storedTimetableState: {
                timetableV2Selection: {
                    religion: 'ETH',
                    semester: 1,
                },
            },
            storedTimetableStudentContext: {
                student: {
                    studentCode: '100',
                    label: '5K · SOLLEDER Luis · Semester 5',
                    religion: 'Rk',
                    semesterLabel: '1. Semester',
                    email: 'luis.solleder@example.test',
                },
            },
        })
        const fallbackEmailContext = timetableV2Context({
            robotStudents: [
                { student_code: '200', email: 'stale.context@example.test' },
            ],
            storedTimetableStudentContext: {
                student: {
                    studentCode: '200',
                    label: '5K · SOLLEDER Luis · Semester 5',
                },
            },
        })
        const religionSummaryItem = context.storedTimetableSelectionSummary.find((item) => item.key === 'religion')

        expect(source).toContain('class="ttv2-strip__student-religion"')
        expect(source).toContain('class="ttv2-strip__student-email"')
        expect(source).toContain('{{ storedTimetableStudentReligionMeta() }}')
        expect(TimetableV2.computed.storedTimetableStudentLabel.call(context)).toBe('5K · SOLLEDER Luis · Semester 5')
        expect(TimetableV2.computed.storedTimetableStudentEmail.call(context)).toBe('luis.solleder@example.test')
        expect(TimetableV2.computed.storedTimetableStudentEmail.call(fallbackEmailContext)).toBe('stale.context@example.test')
        expect(TimetableV2.methods.storedTimetableStudentReligionMeta.call(context)).toBe('Religion: Rk')
        expect(religionSummaryItem).toMatchObject({
            key: 'religion',
            label: 'Ethik / Religion',
        })
        expect(religionSummaryItem?.options.map((option) => option.value)).toEqual(['ETH', 'Rk'])
    })

    it('shows passed retry courses as completed with the grade trend', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const courseSelectionCardsSource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/CourseSelectionCards.vue', 'utf8')
        const context = timetableV2Context()
        const courseHistory = TimetableV2.methods.overviewStudentCourseHistoryFromSummary.call(context, {
            completed_courses: [
                {
                    code: 'BU1',
                    grade: 'N',
                },
                {
                    code: 'BU1',
                    grade: '4',
                    hours: 2,
                },
                {
                    code: 'D1',
                    grade: 'N',
                },
            ],
        })

        expect(courseHistory.completed.map((course) => ({
            code: course.code,
            hours: course.hours,
            label: course.label,
            meta: course.meta,
        }))).toEqual([
            {
                code: 'BU1',
                hours: 2,
                label: 'BU1',
                meta: 'N|4',
            },
        ])
        expect(courseHistory.failed.map((course) => ({
            code: course.code,
            label: course.label,
            meta: course.meta,
        }))).toEqual([
            {
                code: 'D1',
                label: 'D1',
                meta: 'N',
            },
        ])
        expect(courseSelectionCardsSource).toContain('students-timetable-v2-completed-courses__item--completed')
        expect(source).toContain("items: this.storedCompletedCourseItems.map((course) => this.courseSelectionCardItem(course, 'completed'))")
    })

    it('builds negative course history from missing courses', () => {
        const context = timetableV2Context()
        const courseHistory = TimetableV2.methods.overviewStudentCourseHistoryFromSummary.call(context, {
            completed_courses: [
                {
                    code: 'D6',
                    grade: '4',
                },
            ],
            missing_courses: [
                {
                    code: 'BU1',
                    grade: '5',
                    hours: 2,
                },
                {
                    code: 'CH2',
                    grade: 'N',
                    hours: 2,
                },
            ],
        })

        expect(courseHistory.failed.map((course) => ({
            code: course.code,
            label: course.label,
            meta: course.meta,
        }))).toEqual([
            {
                code: 'BU1',
                label: 'BU1',
                meta: '5',
            },
            {
                code: 'CH2',
                label: 'CH2',
                meta: 'N',
            },
        ])
        expect(courseHistory.missing.map((course) => ({
            code: course.code,
            hours: course.hours,
            label: course.label,
        }))).toEqual([
            {
                code: 'BU1',
                hours: 2,
                label: 'BU1',
            },
            {
                code: 'CH2',
                hours: 2,
                label: 'CH2',
            },
        ])
    })

    it('shows negative course cards from missing course summary items', () => {
        const context = timetableV2Context()
        const courseHistory = TimetableV2.methods.overviewStudentCourseHistoryFromSummary.call(context, {
            missing_courses: [
                {
                    code: 'BU1',
                    grade: '5',
                    hours: 2,
                },
                {
                    code: 'CH2',
                    grade: 'N',
                    hours: 2,
                },
            ],
            proposed_courses: [
                {
                    code: 'D6',
                    hours: 3,
                    label: 'D6',
                },
            ],
        })

        context.storedTimetableStudentContext = {
            courses: courseHistory,
        }
        Object.defineProperty(context, 'storedMissingCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedMissingCourseItems.call(context)
            },
        })
        Object.defineProperty(context, 'storedMissingCourseCardItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedMissingCourseCardItems.call(context)
            },
        })
        Object.defineProperty(context, 'storedPlannedCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedPlannedCourseItems.call(context)
            },
        })

        expect(context.storedMissingCourseItems.map((course) => course.code)).toEqual(['BU1', 'CH2'])
        expect(context.storedMissingCourseCardItems.map((course) => course.code)).toEqual(['BU1', 'CH2'])
        expect(context.storedPlannedCourseItems.map((course) => course.code)).toEqual(['D6'])
    })

    it('keeps negative language course hours after changing the selected language', () => {
        const context = timetableV2Context({
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'missing:F2': true,
                    },
                    language: 'L',
                    semester: 2,
                },
                transferredStudentContext: null,
            },
            storedTimetableStudentContext: {
                courses: {
                    failed: [
                        { code: 'F2', grade: '5', hours: 4, label: 'F2' },
                    ],
                    missing: [
                        { code: 'F2', label: 'F2' },
                    ],
                },
            },
            subjectRows: [
                {
                    id: 1,
                    semester: 2,
                    branch: 'common',
                    json_code: 'F2',
                    json_subject: 'F',
                    name: 'Französisch 2',
                    hours_per_week: 4,
                },
            ],
        })

        expect(context.effectiveTimetableV2Selection.language).toBe('L')
        expect(context.storedMissingCourseItems[0]).toMatchObject({
            code: 'F2',
            hours: 4,
            hoursMeta: '4 Std.',
        })
        expect(context.storedMissingCourseCardItems[0]).toMatchObject({
            code: 'F2',
            hours: 4,
            hoursMeta: '4 Std.',
        })
        expect(context.selectedCourseItems.find((course) => course.code === 'F2')).toMatchObject({
            label: 'F2',
            meta: '4 Std.',
        })
    })

    it('renders selected student default semester courses with course labels', () => {
        const context = timetableV2Context({
            courseGroupsLoaded: true,
            courseGroups: [
                { course: 'D3', title: 'D3' },
                { course: 'E3', title: 'E3' },
                { course: 'GW1', title: 'GW1' },
            ],
            subjectRows: [
                { id: 1, semester: 4, branch: 'common', json_code: 'D3', json_subject: 'D', name: 'Deutsch 3', hours_per_week: 3 },
                { id: 2, semester: 4, branch: 'common', json_code: 'E3', json_subject: 'E', name: 'Englisch 3', hours_per_week: 3 },
                { id: 3, semester: 4, branch: 'common', json_code: 'GW1', json_subject: 'GW', name: 'Geografie 1', hours_per_week: 4 },
            ],
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 4',
                },
                courses: {
                    completed: [],
                    failed: [],
                    missing: [],
                    planned: [],
                },
            },
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    semester: 4,
                },
                transferredStudentContext: {
                    student: {
                        semesterLabel: 'Semester 4',
                    },
                    courses: {
                        completed: [],
                        failed: [],
                        missing: [],
                        planned: [],
                    },
                },
            },
        })

        Object.defineProperty(context, 'storedPlannedCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedPlannedCourseItems.call(context)
            },
        })

        const semesterCardItems = context.storedSemesterCourseItems
            .map((course) => TimetableV2.methods.courseSelectionCardItem.call(context, course, 'semester'))

        expect(semesterCardItems.map((course) => course.label)).toEqual(['D3', 'E3', 'GWB1'])
        expect(semesterCardItems.map((course) => course.meta)).toEqual(['3 Std.', '3 Std.', '4 Std.'])
        expect(semesterCardItems.every((course) => course.label.trim() !== '')).toBe(true)
        expect(semesterCardItems.every((course) => course.meta.trim() !== '')).toBe(true)
    })

    it('does not list selected religion defaults as planned when generic religion modules are completed', () => {
        const context = timetableV2Context({
            subjectRows: [
                { id: 1, semester: 1, branch: 'common', json_code: 'R1', json_subject: 'R/ET', name: 'Religion 1', hours_per_week: 1 },
                { id: 2, semester: 2, branch: 'common', json_code: 'R2', json_subject: 'R/ET', name: 'Religion 2', hours_per_week: 1 },
                { id: 3, semester: 3, branch: 'common', json_code: 'R3', json_subject: 'R/ET', name: 'Religion 3', hours_per_week: 1 },
                { id: 4, semester: 4, branch: 'common', json_code: 'R4', json_subject: 'R/ET', name: 'Religion 4', hours_per_week: 1 },
            ],
            storedTimetableStudentContext: {
                student: {
                    religion: 'islam. (IGGÖ)',
                    semesterLabel: 'Semester 4',
                },
                courses: {
                    completed: [
                        { code: 'R1', grade: '1' },
                        { code: 'R2', grade: '1' },
                        { code: 'R3', grade: '1' },
                        { code: 'R4', grade: '1' },
                    ],
                    failed: [],
                    missing: [],
                    planned: [],
                },
            },
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    religion: 'Ris',
                    semester: 4,
                },
                transferredStudentContext: null,
            },
        })

        Object.defineProperty(context, 'storedPlannedCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedPlannedCourseItems.call(context)
            },
        })

        expect(context.storedCompletedCourseItems.map((course) => course.code)).toEqual(['R1', 'R2', 'R3', 'R4'])
        expect(context.selectedStudentDefaultSemesterCourses().map((course) => course.code)).toEqual(['Ris4'])
        expect(context.storedSemesterCourseItems.map((course) => course.code)).toEqual([])
        expect(context.storedPlannedCourseItems.map((course) => course.code)).toEqual([])
    })

    it('limits selected courses by current course groups only', () => {
        const additionalCourseItems = [
            { code: 'INF2', hours: 8, label: 'INF2' },
            { code: 'GS1', hours: 8, label: 'GS1' },
            { code: 'CH1', hours: 8, label: 'CH1' },
            { code: 'GW1', hours: 8, label: 'GW1' },
        ]
        const context = timetableV2Context({
            courseSelectionOverrides: {
                ...Object.fromEntries(additionalCourseItems.map((course) => [`additional:${course.code}`, true])),
                'missing:D1': true,
                'missing:BU1': true,
            },
            storedAdditionalCourseItems: additionalCourseItems,
            storedMissingCourseCardItems: [
                { code: 'D1', hours: 4, label: 'D1' },
                { code: 'BU1', hours: 3, label: 'BU1' },
            ],
            storedPlannedCourseItems: [
                { code: 'M1', hours: 3, label: 'M1' },
            ],
        })

        expect(context.selectedCourseSummary.count).toBe(6)
        expect(context.selectedCourseSummary.hours).toBe(39)
        expect(context.selectedCourseLimitSummary.count).toBe(2)
        expect(context.selectedCourseLimitSummary.hours).toBe(7)
        expect(context.selectedCourseLimitReached).toBe(false)
        expect(context.selectedCourseLimitExceeded).toBe(false)
    })

    it('selects only selected semester default courses and matching failed courses by default', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 4',
                },
                courses: {
                    completed: [],
                    failed: [
                        { code: 'BU2', grade: 'N' },
                        { code: 'D3', grade: 'N' },
                    ],
                    missing: [
                        { code: 'BU2', hours: 3, label: 'BU2' },
                        { code: 'D3', hours: 3, label: 'D3' },
                    ],
                    planned: [
                        { code: 'D3', hours: 3, label: 'D3' },
                        { code: 'M3', hours: 4, label: 'M3' },
                    ],
                },
            },
            subjectRows: [
                { id: 1, semester: 3, branch: 'common', json_code: 'D3', json_subject: 'D', name: 'Deutsch 3', hours_per_week: 3 },
                { id: 2, semester: 3, branch: 'common', json_code: 'M3', json_subject: 'M', name: 'Mathematik 3', hours_per_week: 4 },
                { id: 3, semester: 4, branch: 'common', json_code: 'BU2', json_subject: 'BU', name: 'Biologie 2', hours_per_week: 3 },
                { id: 4, semester: 4, branch: 'common', json_code: 'M4', json_subject: 'M', name: 'Mathematik 4', hours_per_week: 4 },
                { id: 5, semester: 4, branch: 'common', json_code: 'D4', json_subject: 'D', name: 'Deutsch 4', hours_per_week: 3 },
            ],
        })

        expect(context.storedMissingCourseCardItems.map((course) => course.code)).toEqual(['BU2', 'D3'])
        expect(context.storedSemesterCourseItems.map((course) => course.code)).toEqual(['D4', 'M4'])
        expect(context.storedPlannedCourseItems.map((course) => course.code)).toEqual(['M3'])
        expect(context.selectedMissingCourseCardItems.map((course) => course.code)).toEqual(['BU2'])
        expect(context.selectedSemesterCourseItems.map((course) => course.code)).toEqual([])
        expect(context.selectedPlannedCourseItems.map((course) => course.code)).toEqual([])
        expect(context.selectedCourseItems.map((course) => course.code)).toEqual(['BU2'])
    })

    it('allows explicitly selected current semester courses when the previous module is unfinished', () => {
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'semester:D4': true,
            },
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 6',
                },
                courses: {
                    completed: [
                        { code: 'D1', grade: '2' },
                        { code: 'D2', grade: '3' },
                    ],
                    failed: [],
                    missing: [],
                    planned: [
                        { code: 'D3', hours: 3, label: 'D3' },
                    ],
                },
            },
            subjectRows: [
                { id: 1, semester: 3, branch: 'common', json_code: 'D3', json_subject: 'D', name: 'Deutsch 3', hours_per_week: 3 },
                { id: 2, semester: 6, branch: 'common', json_code: 'D4', json_subject: 'D', name: 'Deutsch 4', hours_per_week: 3 },
                { id: 3, semester: 6, branch: 'common', json_code: 'M4', json_subject: 'M', name: 'Mathematik 4', hours_per_week: 4 },
            ],
        })

        expect(context.storedSemesterCourseItems.map((course) => course.code)).toEqual(['D4', 'M4'])
        expect(context.storedPlannedCourseItems.map((course) => course.code)).toEqual(['D3'])
        expect(context.selectedSemesterCourseItems.map((course) => course.code)).toEqual(['D4', 'M4'])
        expect(context.courseItemDefaultSelected(context.storedSemesterCourseItems[0], 'semester')).toBe(false)
        expect(context.courseItemSelected(context.storedSemesterCourseItems[0], 'semester')).toBe(true)
        expect(context.courseSelectedBySelections(context.storedSemesterCourseItems[0], 'semester', {
            'semester:D4': true,
        })).toBe(true)
        expect(context.courseSelectedBySelections(context.storedSemesterCourseItems[0], 'semester', {})).toBe(false)

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context, {
            includeQualityCounters: false,
        })

        expect(payload.selected_course_keys).toContain('2|6|common|D4|D|Deutsch 4|D4')
    })

    it('lists future planned courses as additional courses for the selected student semester', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 4',
                },
                courses: {
                    completed: [],
                    failed: [],
                    missing: [],
                    planned: [
                        { code: 'ME1', hours: 2, label: 'ME1' },
                        { code: 'D3', hours: 3, label: 'D3' },
                    ],
                    additional: [],
                },
            },
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    artsSubject: 'ME',
                    semester: 4,
                },
                transferredStudentContext: null,
            },
            subjectRows: [
                { id: 1, semester: 3, branch: 'common', json_code: 'D3', json_subject: 'D', name: 'Deutsch 3', hours_per_week: 3 },
                { id: 2, semester: 7, branch: 'common', json_code: 'ME1', json_subject: 'ME', name: 'Musikerziehung 1', hours_per_week: 2 },
            ],
        })

        Object.defineProperty(context, 'storedPlannedCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedPlannedCourseItems.call(context)
            },
        })
        Object.defineProperty(context, 'storedAdditionalCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedAdditionalCourseItems.call(context)
            },
        })

        expect(context.storedPlannedCourseItems.map((course) => course.code)).toEqual(['D3'])
        expect(context.storedAdditionalCourseItems.map((course) => course.code)).toEqual(['ME1'])
        expect(context.selectedAdditionalCourseItems.map((course) => course.code)).toEqual([])
    })

    it('lists additional courses due by the selected student semester as earlier modules', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 4',
                },
                courses: {
                    completed: [],
                    failed: [],
                    missing: [],
                    planned: [
                        { code: 'GWB1', hours: 4, label: 'GWB1', semester: 2 },
                    ],
                    additional: [
                        { code: 'GWB2', hours: 4, label: 'GWB2', semester: 3 },
                        { code: 'ME1', hours: 2, label: 'ME1', semester: 7 },
                    ],
                },
            },
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    semester: 4,
                },
                transferredStudentContext: null,
            },
        })

        Object.defineProperty(context, 'storedAdditionalCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedAdditionalCourseItems.call(context)
            },
        })

        expect(context.storedPlannedCourseItems.map((course) => course.code)).toEqual(['GWB1', 'GWB2'])
        expect(context.storedAdditionalCourseItems.map((course) => course.code)).toEqual(['ME1'])
        expect(context.moreCoursesCategoryCards.find((card) => card.key === 'planned')?.items.map((course) => course.label))
            .toEqual(['GWB1', 'GWB2'])
        expect(context.moreCoursesCategoryCards.find((card) => card.key === 'additional')?.items.map((course) => course.label))
            .toEqual(['ME1'])
    })

    it('does not list current semester courses as additional courses', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 4',
                },
                courses: {
                    completed: [],
                    failed: [
                        { code: 'BU2', grade: 'N' },
                        { code: 'INF2', grade: '5' },
                    ],
                    missing: [
                        { code: 'BU2', hours: 3, label: 'BU2' },
                        { code: 'INF2', hours: 4, label: 'INF2' },
                    ],
                    planned: [],
                    additional: [
                        { code: 'BU2', hours: 3, label: 'BU2' },
                        { code: 'D4', hours: 4, label: 'D4' },
                        { code: 'GW2', hours: 4, label: 'GW2' },
                        { code: 'INF2', hours: 4, label: 'INF2' },
                        { code: 'M5', hours: 3, label: 'M5' },
                    ],
                },
            },
            subjectRows: [
                { id: 1, semester: 4, branch: 'common', json_code: 'BU2', json_subject: 'BU', name: 'Biologie 2', hours_per_week: 3 },
                { id: 2, semester: 4, branch: 'common', json_code: 'D4', json_subject: 'D', name: 'Deutsch 4', hours_per_week: 4 },
            ],
        })

        Object.defineProperty(context, 'storedAdditionalCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedAdditionalCourseItems.call(context)
            },
        })

        expect(context.storedMissingCourseCardItems.map((course) => course.code)).toEqual(['BU2', 'INF2'])
        expect(context.storedSemesterCourseItems.map((course) => course.code)).toEqual(['D4'])
        expect(context.storedAdditionalCourseItems.map((course) => course.code)).toEqual(['GW2', 'M5'])
    })

    it('does not list completed courses as additional courses', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                courses: {
                    completed: [
                        { code: 'INF2', grade: '4', hours: 4, label: 'INF2' },
                    ],
                    failed: [],
                    missing: [],
                    planned: [],
                    additional: [
                        { code: 'INF2', hours: 4, label: 'INF2' },
                        { code: 'GW2', hours: 4, label: 'GW2' },
                    ],
                },
            },
        })

        Object.defineProperty(context, 'storedAdditionalCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedAdditionalCourseItems.call(context)
            },
        })

        expect(context.storedCompletedCourseItems.map((course) => course.code)).toEqual(['INF2'])
        expect(context.storedAdditionalCourseItems.map((course) => course.code)).toEqual(['GW2'])
    })

    it('trims selected semester default courses to the course limit during preselection', () => {
        const storedSemesterCourseItems = Array.from({ length: 11 }, (_, index) => ({
            code: `S${index + 1}`,
            hours: 3,
            label: `S${index + 1}`,
        }))
        const context = timetableV2Context({
            storedSemesterCourseItems,
        })

        const courseSelections = TimetableV2.methods.courseSelectionsForCourseLimitPreselection.call(context, {})
        const selectedLimitCourseItems = TimetableV2.methods.courseLimitSelectedCourseItems.call(context, courseSelections)
        const selectedLimitCourseHours = selectedLimitCourseItems
            .reduce((hours, courseItem) => hours + TimetableV2.methods.courseHoursNumber.call(context, courseItem.course), 0)
        const disabledSemesterCourses = Object.entries(courseSelections)
            .filter(([selectionKey, selected]) => selectionKey.startsWith('semester:') && selected === false)

        expect(selectedLimitCourseItems).toHaveLength(10)
        expect(selectedLimitCourseHours).toBe(30)
        expect(disabledSemesterCourses).toHaveLength(1)
    })

    it('keeps unselected current semester courses when applying preselection', () => {
        const storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                courseLimitPreselectionKey: '',
                courseSelections: {
                    'semester:D4': false,
                    'additional:GW2': true,
                },
            },
            transferredStudentContext: null,
        }
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            courseCardsVisible: true,
            saveStoredTimetableState,
            storedAdditionalCourseItems: [
                { code: 'GW2', hours: 4, label: 'GW2' },
            ],
            storedSemesterCourseItems: [
                { code: 'D4', hours: 4, label: 'D4' },
            ],
            storedTimetableState,
            storedTimetableStateForSaving: () => storedTimetableState,
        })

        storedTimetableState.timetableV2Selection.courseLimitPreselectionKey = context.courseLimitPreselectionSignature

        expect(context.courseItemSelected(context.storedSemesterCourseItems[0], 'semester')).toBe(false)

        TimetableV2.methods.applyCourseLimitPreselection.call(context)

        expect(saveStoredTimetableState).not.toHaveBeenCalled()
        expect(context.courseSelectionsForCourseLimitPreselection(context.courseSelectionOverrides)).toEqual({
            'additional:GW2': true,
            'semester:D4': false,
        })
    })

    it('does not list negative courses as planned courses', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                courses: {
                    failed: [
                        { code: 'E2', grade: 'N' },
                        { code: 'THEA6', grade: '5' },
                    ],
                    missing: [
                        { code: 'E2', hours: 3, label: 'E2' },
                        { code: 'M1', hours: 4, label: 'M1' },
                    ],
                    planned: [
                        { code: 'D1', hours: 3, label: 'D1' },
                    ],
                },
            },
        })
        Object.defineProperty(context, 'storedMissingCourseCardItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedMissingCourseCardItems.call(context)
            },
        })
        Object.defineProperty(context, 'storedMissingCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedMissingCourseItems.call(context)
            },
        })

        expect(TimetableV2.computed.storedMissingCourseItems.call(context).map((course) => course.code)).toEqual(['E2', 'THEA6'])
        expect(TimetableV2.computed.storedMissingCourseCardItems.call(context).map((course) => course.code)).toEqual(['E2'])
        expect(TimetableV2.computed.storedPlannedCourseItems.call(context).map((course) => course.code)).toEqual(['D1', 'M1'])
    })

    it('blocks selected semester default modules when a prerequisite module is negative', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 4',
                },
                courses: {
                    failed: [
                        { code: 'E2', grade: 'N' },
                    ],
                    missing: [
                        { code: 'E2', hours: 3, label: 'E2' },
                    ],
                    planned: [],
                },
            },
            subjectRows: [
                { id: 1, semester: 2, branch: 'common', json_code: 'E2', json_subject: 'E', name: 'Englisch 2', hours_per_week: 3 },
                { id: 2, semester: 4, branch: 'common', json_code: 'E3', json_subject: 'E', name: 'Englisch 3', hours_per_week: 3 },
                { id: 3, semester: 4, branch: 'common', json_code: 'E4', json_subject: 'E', name: 'Englisch 4', hours_per_week: 3 },
            ],
        })
        Object.defineProperty(context, 'storedMissingCourseCardItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedMissingCourseCardItems.call(context)
            },
        })
        Object.defineProperty(context, 'storedMissingCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedMissingCourseItems.call(context)
            },
        })
        Object.defineProperty(context, 'storedPlannedCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedPlannedCourseItems.call(context)
            },
        })

        const semesterE3Course = context.storedSemesterCourseItems.find((course) => course.code === 'E3')

        expect(context.storedMissingCourseCardItems.map((course) => course.code)).toEqual(['E2'])
        expect(context.selectedStudentDefaultSemesterCourses().map((course) => course.code)).toEqual(['E3', 'E4'])
        expect(context.storedSemesterCourseItems.map((course) => course.code)).toEqual(['E3'])
        expect(context.selectedSemesterCourseItems.map((course) => course.code)).toEqual([])
        expect(context.courseItemSelected(semesterE3Course, 'semester')).toBe(false)
    })

    it('keeps a follow-up course out of current courses when an earlier prerequisite is negative', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 4',
                },
                courses: {
                    failed: [
                        { code: 'E2', grade: '5' },
                    ],
                    missing: [
                        { code: 'E2', hours: 3, label: 'E2' },
                    ],
                    planned: [
                        { code: 'E3', hours: 3, label: 'E3' },
                    ],
                },
            },
            subjectRows: [
                { id: 1, semester: 2, branch: 'common', json_code: 'E2', json_subject: 'E', name: 'Englisch 2', hours_per_week: 3 },
                { id: 2, semester: 3, branch: 'common', json_code: 'E3', json_subject: 'E', name: 'Englisch 3', hours_per_week: 3 },
                { id: 3, semester: 4, branch: 'common', json_code: 'E4', json_subject: 'E', name: 'Englisch 4', hours_per_week: 3 },
            ],
        })

        expect(context.storedMissingCourseCardItems.map((course) => course.code)).toEqual(['E2'])
        expect(context.storedPlannedCourseItems.map((course) => course.code)).toEqual(['E3'])
        expect(context.storedSemesterCourseItems.map((course) => course.code)).toEqual([])
        expect(context.selectedCourseItems.map((course) => course.code)).toEqual([])
    })

    it('does not preselect negative course modules by default', () => {
        const context = timetableV2Context({
            storedMissingCourseCardItems: [
                { code: 'BU1-N', hours: 3, label: 'BU1' },
                { code: 'BU2-N', hours: 3, label: 'BU2' },
            ],
        })

        const negativeBu1Course = context.storedMissingCourseCardItems.find((course) => course.label === 'BU1')
        const negativeBu2Course = context.storedMissingCourseCardItems.find((course) => course.label === 'BU2')

        expect(context.courseBaseCode(negativeBu1Course)).toBe('BU')
        expect(context.courseModuleNumber(negativeBu1Course)).toBe(1)
        expect(context.courseItemSelected(negativeBu1Course, 'missing')).toBe(false)
        expect(context.courseItemSelected(negativeBu2Course, 'missing')).toBe(false)
        expect(context.selectedMissingCourseCardItems.map((course) => course.label)).toEqual([])
    })

    it('preselects negative courses when they are default courses for the selected semester', () => {
        const context = timetableV2Context({
            storedMissingCourseCardItems: [
                { code: 'BU1', hours: 3, label: 'BU1' },
                { code: 'CH2', hours: 3, label: 'CH2' },
            ],
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 4',
                },
                courses: {},
            },
            subjectRows: [
                { id: 1, semester: 4, branch: 'common', json_code: 'BU1', json_subject: 'BU', name: 'Biologie 1', hours_per_week: 3 },
            ],
        })

        const negativeBu1Course = context.storedMissingCourseCardItems.find((course) => course.label === 'BU1')
        const negativeCh2Course = context.storedMissingCourseCardItems.find((course) => course.label === 'CH2')

        expect(context.courseItemSelected(negativeBu1Course, 'missing')).toBe(true)
        expect(context.courseItemSelected(negativeCh2Course, 'missing')).toBe(false)
        expect(context.selectedMissingCourseCardItems.map((course) => course.label)).toEqual(['BU1'])
    })

    it('enables the course preselection reset only after course selection changes', () => {
        const context = timetableV2Context({
            courseCardsVisible: true,
            storedMissingCourseCardItems: [
                { code: 'BU1', hours: 3, label: 'BU1' },
                { code: 'BU2', hours: 3, label: 'BU2' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                timetableV2Options: {
                    maxFreeDays: true,
                    noDistanceLearning: true,
                    noSaturday: false,
                    startsFromPeriod10: false,
                },
                transferredStudentContext: null,
            },
        })

        expect(context.courseLimitPreselectionResetAvailable).toBe(false)

        context.storedTimetableState.timetableV2Selection.courseSelections = {
            'missing:BU2': true,
        }

        expect(context.courseLimitPreselectionResetAvailable).toBe(true)
    })

    it('keeps active course card selections when opening course review from selection', () => {
        const storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                semester: 5,
                courseLimitPreselectionKey: 'old',
                courseSelections: {
                    'planned:GW1': false,
                    'additional:INF2': true,
                },
                offeredCourseSelections: {
                    'planned:GW1::fu': false,
                },
                moreOfferedCourseSelections: {
                    'more:GS1::regular': true,
                },
                includeDistanceLearningCourses: false,
                includeKompaktunterrichtCourses: false,
            },
            transferredStudentContext: null,
        }
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            courseCardsVisible: true,
            draftCourseSelections: {
                'completed:C1': true,
                'missing:BU1': true,
                'planned:GW1': false,
                'semester:D5': false,
                'additional:INF2': true,
            },
            storedCompletedCourseItems: [
                { code: 'C1', hours: 2, label: 'C1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, label: 'INF2' },
            ],
            storedMissingCourseCardItems: [
                { code: 'BU1', hours: 3, label: 'BU1' },
            ],
            storedPlannedCourseItems: [
                { code: 'GW1', hours: 3, label: 'GW1' },
            ],
            storedSemesterCourseItems: [
                { code: 'D5', hours: 3, label: 'D5' },
            ],
            storedTimetableState,
            storedTimetableStateForSaving: () => storedTimetableState,
            saveStoredTimetableState,
        })

        TimetableV2.methods.openCourseReview.call(context)

        expect(saveStoredTimetableState).toHaveBeenCalledOnce()
        expect(saveStoredTimetableState).toHaveBeenCalledWith({
            selection: {},
            timetableV2Selection: {
                semester: 5,
                courseLimitPreselectionKey: expect.any(String),
                courseSelections: {
                    'additional:INF2': true,
                    'completed:C1': true,
                    'missing:BU1': true,
                    'planned:GW1': false,
                    'semester:D5': false,
                },
            },
            transferredStudentContext: null,
        })
        expect(context.timetableV2Step).toBe('course-review')
        expect(context.loadCourseGroups).toHaveBeenCalledOnce()
        expect(context.loadSchoolHours).toHaveBeenCalledOnce()
    })

    it('keeps a deselected subject-row semester course out of course review from selection', () => {
        let context: ReturnType<typeof timetableV2Context>
        const storedTimetableState = {
            selection: {},
            timetableV2Selection: {},
            transferredStudentContext: null,
        }
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            courseCardsVisible: true,
            draftCourseSelections: {
                'semester:M4': false,
                'semester:subject-row-m4': true,
            },
            saveStoredTimetableState,
            storedSemesterCourseItems: [
                { code: 'CH1', hours: 3, key: 'CH1', label: 'CH1' },
                { hours: 3, key: 'subject-row-m4', label: 'M4' },
            ],
            storedTimetableState,
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
        })
        const m4CardItem = TimetableV2.methods.courseSelectionCardItem.call(
            context,
            context.storedSemesterCourseItems[1],
            'semester',
        )

        expect(m4CardItem.selectionKey).toBe('semester:M4')
        expect(m4CardItem.selectionKeys).toEqual(['semester:M4', 'semester:subject-row-m4'])
        expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['CH1'])

        TimetableV2.methods.openCourseReview.call(context)

        expect(saveStoredTimetableState).toHaveBeenCalledOnce()
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'semester:M4': false,
            'semester:subject-row-m4': true,
        })
        expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['CH1'])
        expect(context.timetableV2Step).toBe('course-review')
    })

    it('preserves deselected default semester courses when refreshing course limit preselection', () => {
        const context = timetableV2Context({
            courseCardsVisible: true,
            storedSemesterCourseItems: [
                { code: 'CH1', hours: 3, key: 'CH1', label: 'CH1' },
                { hours: 3, key: 'subject-row-m4', label: 'M4' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
        })

        const courseSelections = TimetableV2.methods.courseSelectionsForCourseLimitPreselection.call(context, {
            'semester:CH1': true,
            'semester:M4': false,
            'semester:subject-row-m4': false,
        })

        expect(courseSelections).toEqual({
            'semester:M4': false,
            'semester:subject-row-m4': false,
        })
        expect(TimetableV2.methods.courseSelectedBySelections.call(
            context,
            context.storedSemesterCourseItems[1],
            'semester',
            courseSelections,
        )).toBe(false)
    })

    it('resets preselection across all course cards', () => {
        const storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                courseSelections: {
                    'additional:INF2': true,
                    'completed:C1': true,
                    'missing:BU1': true,
                    'planned:GW1': true,
                    'semester:D5': false,
                },
            },
            transferredStudentContext: null,
        }
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            courseCardsVisible: true,
            storedCompletedCourseItems: [
                { code: 'C1', hours: 2, label: 'C1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, label: 'INF2' },
            ],
            storedMissingCourseCardItems: [
                { code: 'BU1', hours: 3, label: 'BU1' },
            ],
            storedPlannedCourseItems: [
                { code: 'GW1', hours: 3, label: 'GW1' },
            ],
            storedSemesterCourseItems: [
                { code: 'D5', hours: 3, label: 'D5' },
            ],
            storedTimetableState,
            storedTimetableStateForSaving: () => storedTimetableState,
            saveStoredTimetableState,
        })

        TimetableV2.methods.applyCourseLimitPreselection.call(context, true)

        expect(saveStoredTimetableState).toHaveBeenCalledWith({
            selection: {},
            timetableV2Selection: {
                courseLimitPreselectionKey: expect.any(String),
            },
            transferredStudentContext: null,
        })
    })

    it('uses real course keys for negative courses in the timetable calculation payload', () => {
        const backendCourseKey = '87|2|common|BU1|BU|Biologie 1|BU1'
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'missing:BU1': true,
            },
            storedTimetableStudentContext: {
                courses: {
                    failed: [
                        { code: 'BU1', grade: 'N' },
                    ],
                    missing: [
                        { code: 'BU1', hours: 3, key: backendCourseKey, label: 'BU1' },
                    ],
                    planned: [],
                },
            },
        })

        Object.defineProperty(context, 'storedMissingCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedMissingCourseItems.call(context)
            },
        })
        Object.defineProperty(context, 'storedMissingCourseCardItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedMissingCourseCardItems.call(context)
            },
        })

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.storedMissingCourseCardItems[0].key).toBe(backendCourseKey)
        expect(payload.selected_course_keys).toEqual([backendCourseKey])
        expect(payload.selected_course_keys).not.toContain('missing-BU1-N')
    })

    it('marks courses without offered courses as unavailable and not selectable', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'planned:D1': true,
            },
            courseGroups: [
                { class_name: 'D1-1C-GOS', course: 'D1', hour: 1, title: 'D1', weekday: 1 },
            ],
            courseGroupsLoaded: true,
            saveStoredTimetableState,
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, key: 'D1', label: 'D1' },
                { code: 'Ris3', hours: 2, key: 'Ris3', label: 'Ris3' },
            ],
        })
        const availableCourse = context.storedPlannedCourseItems[0]
        const unavailableCourse = context.storedPlannedCourseItems[1]

        expect(source).toContain("'students-timetable-v2-completed-courses__item--unavailable': unavailable")
        expect(source).toContain('title: this.courseItemSelectionDisabledLabel(course, courseGroup)')
        expect(context.courseItemUnavailable(availableCourse, 'planned')).toBe(false)
        expect(context.courseItemUnavailable(unavailableCourse, 'planned')).toBe(true)
        expect(context.courseItemSelected(availableCourse, 'planned')).toBe(true)
        expect(context.courseItemSelected(unavailableCourse, 'planned')).toBe(false)
        expect(context.selectedPlannedCourseItems.map((course) => course.code)).toEqual(['D1'])
        expect(context.courseItemSelectionDisabled(unavailableCourse, 'planned')).toBe(true)
        expect(context.courseItemSelectionDisabledLabel(unavailableCourse, 'planned')).toBe('Kein angebotenes Modul vorhanden')
        expect(context.courseSelectedBySelections(unavailableCourse, 'planned', {
            'planned:RIS3': true,
        })).toBe(true)

        context.toggleCourseItem(unavailableCourse, 'planned')

        expect(saveStoredTimetableState).not.toHaveBeenCalled()
    })

    it('shows the course limit info and blocks adding more missing or planned courses at the maximum', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const courseSelectionCardsSource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/CourseSelectionCards.vue', 'utf8')
        const selectedCourseItems = Array.from({ length: 10 }, (_, index) => ({
            code: `M${index + 1}`,
            hours: 3,
            label: `M${index + 1}`,
        }))
        const extraCourse = { code: 'BU1', hours: 2, label: 'BU1' }
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'planned:BU1': false,
                ...Object.fromEntries(selectedCourseItems.map((course) => [`missing:${course.code}`, true])),
            },
            saveStoredTimetableState,
            storedMissingCourseCardItems: selectedCourseItems,
            storedPlannedCourseItems: [extraCourse],
        })

        expect(source).toContain('v-if="courseCardsVisible && selectedCourseLimitReached"')
        expect(source).toContain("title: 'Zusätzliche Module'")
        expect(courseSelectionCardsSource).toMatch(/class="students-timetable-v2-course-selection-summary"[\s\S]*Ausgewählt[\s\S]*\{\{ selectedCourseLimitSummary\.countLabel \}\}[\s\S]*\{\{ selectedCourseLimitSummary\.hoursLabel \}\}[\s\S]*Maximal 10\/30/u)
        expect(source).toContain('Maximum erreicht: Negative Module und Frühere Module')
        expect(source).toContain('students-timetable-v2-completed-courses__item--limit-disabled')
        expect(context.selectedCourseLimitSummary.count).toBe(10)
        expect(context.selectedCourseLimitSummary.hours).toBe(30)
        expect(context.selectedCourseLimitReached).toBe(true)
        expect(context.selectedCourseLimitExceeded).toBe(false)
        expect(TimetableV2.methods.courseItemSelectionDisabled.call(context, extraCourse, 'planned')).toBe(true)
        expect(TimetableV2.methods.courseItemSelectionDisabled.call(context, selectedCourseItems[0], 'missing')).toBe(false)
        expect(TimetableV2.methods.courseGroupSelectionWouldExceedLimit.call(context, 'planned')).toBe(true)

        TimetableV2.methods.toggleCourseItem.call(context, extraCourse, 'planned')

        expect(saveStoredTimetableState).not.toHaveBeenCalled()
    })

    it('allows filling the course limit exactly', () => {
        const selectedCourseItems = Array.from({ length: 9 }, (_, index) => ({
            code: `M${index + 1}`,
            hours: 3,
            label: `M${index + 1}`,
        }))
        const exactFillCourse = { code: 'BU1', hours: 3, label: 'BU1' }
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'planned:BU1': false,
                        ...Object.fromEntries(selectedCourseItems.map((course) => [`missing:${course.code}`, true])),
                    },
                },
                transferredStudentContext: null,
            },
            saveStoredTimetableState,
            storedMissingCourseCardItems: selectedCourseItems,
            storedPlannedCourseItems: [exactFillCourse],
        })

        expect(context.selectedCourseLimitSummary.count).toBe(9)
        expect(context.selectedCourseLimitSummary.hours).toBe(27)
        expect(TimetableV2.methods.courseItemSelectionDisabled.call(context, exactFillCourse, 'planned')).toBe(false)

        TimetableV2.methods.toggleCourseItem.call(context, exactFillCourse, 'planned')

        expect(saveStoredTimetableState).not.toHaveBeenCalled()
        expect(context.draftCourseSelections).toEqual({
            ...Object.fromEntries(selectedCourseItems.map((course) => [`missing:${course.code}`, true])),
            'planned:BU1': true,
        })
        expect(context.courseSelectionDraftChanged).toBe(true)

        TimetableV2.methods.applyDraftCourseSelections.call(context)

        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Selection: {
                courseSelections: {
                    ...Object.fromEntries(selectedCourseItems.map((course) => [`missing:${course.code}`, true])),
                    'planned:BU1': true,
                },
            },
        }))
    })

    it('ignores additional courses when preselecting courses for the course limit', () => {
        const context = timetableV2Context({
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 20, label: 'INF2' },
                { code: 'GS1', hours: 20, label: 'GS1' },
            ],
            storedMissingCourseCardItems: [
                { code: 'D1', hours: 8, label: 'D1' },
                { code: 'BU1', hours: 8, label: 'BU1' },
            ],
            storedPlannedCourseItems: [
                { code: 'M1', hours: 8, label: 'M1' },
                { code: 'GW1', hours: 8, label: 'GW1' },
                { code: 'CH1', hours: 8, label: 'CH1' },
            ],
        })

        const courseSelections = TimetableV2.methods.courseSelectionsForCourseLimitPreselection.call(context, {
            'additional:INF2': true,
            'additional:GS1': true,
        })
        const disabledPlannedCourses = Object.entries(courseSelections)
            .filter(([selectionKey, selected]) => selectionKey.startsWith('planned:') && selected === false)
        const disabledMissingCourses = Object.entries(courseSelections)
            .filter(([selectionKey, selected]) => selectionKey.startsWith('missing:') && selected === false)
        const disabledAdditionalCourses = Object.entries(courseSelections)
            .filter(([selectionKey, selected]) => selectionKey.startsWith('additional:') && selected === false)
        const selectedLimitCourseHours = TimetableV2.methods.courseLimitSelectedCourseItems.call(context, courseSelections)
            .reduce((hours, courseItem) => hours + TimetableV2.methods.courseHoursNumber.call(context, courseItem.course), 0)

        expect(disabledPlannedCourses).toHaveLength(0)
        expect(disabledMissingCourses).toHaveLength(0)
        expect(disabledAdditionalCourses).toEqual([])
        expect(courseSelections['additional:INF2']).toBe(true)
        expect(courseSelections['additional:GS1']).toBe(true)
        expect(selectedLimitCourseHours).toBeLessThanOrEqual(30)
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
                { class_name: 'D1-1C-GOS', course: 'D1', hour: 11, title: 'D1', weekday: 2 },
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
            { courseGroupLabel: 'Vorgesehen', label: 'D1', meta: '3 Std.' },
            { courseGroupLabel: 'Zusätzlich', label: 'GS1', meta: '4 Std.' },
            { courseGroupLabel: 'Vorgesehen', label: 'GWB1', meta: '4 Std.' },
        ])

        await TimetableV2.methods.toggleMoreCoursesCard.call(context)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreOfferedCourseSelectionChanged).toBe(false)
        const defaultAdditionalCourse = context.moreCoursesCardItems.find((course) => course.label === 'GS1')
        const plannedMoreCourse = context.moreCoursesCardItems.find((course) => course.label === 'GWB1')

        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAllDeselected.call(context, defaultAdditionalCourse)).toBe(true)

        TimetableV2.methods.toggleMoreCourseOffers.call(context, defaultAdditionalCourse)

        const defaultAdditionalOfferedCourse = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, defaultAdditionalCourse)[0]

        expect(context.selectedMoreCourseItem?.label).toBe('GS1')
        expect(context.saveStoredTimetableState).not.toHaveBeenCalled()
        expect(context.moreCoursesSelectionChanged).toBe(true)
        expect(context.moreOfferedCourseSelectionChanged).toBe(false)
        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAnySelected.call(context, defaultAdditionalCourse)).toBe(true)
        expect(TimetableV2.methods.moreCourseDisabled.call(context, plannedMoreCourse)).toBe(true)

        const saveCallCount = context.saveStoredTimetableState.mock.calls.length

        TimetableV2.methods.toggleMoreCourseOffers.call(context, plannedMoreCourse)

        expect(context.selectedMoreCourseItem?.label).toBe('GS1')
        expect(context.saveStoredTimetableState).toHaveBeenCalledTimes(saveCallCount)

        TimetableV2.methods.toggleMoreCourseOffers.call(context, defaultAdditionalCourse)

        expect(context.selectedMoreCourseItem).toBeNull()
        expect(context.saveStoredTimetableState).toHaveBeenCalledTimes(saveCallCount)
        expect(TimetableV2.methods.moreCourseDisabled.call(context, plannedMoreCourse)).toBe(false)

        TimetableV2.methods.toggleMoreCourseOffers.call(context, defaultAdditionalCourse)
        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, defaultAdditionalOfferedCourse)

        context.storedTimetableState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAllDeselected.call(context, defaultAdditionalCourse)).toBe(true)
        expect(TimetableV2.methods.moreCourseDisabled.call(context, plannedMoreCourse)).toBe(false)

        TimetableV2.methods.toggleMoreCourseOffers.call(context, plannedMoreCourse)

        const plannedOfferedCourse = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, plannedMoreCourse)[0]

        expect(context.selectedMoreCourseItem?.label).toBe('GWB1')
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, plannedOfferedCourse, plannedMoreCourse)).toBe(true)

        TimetableV2.methods.cancelMoreCoursesCard.call(context)

        const restoredState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        expect(restoredState.timetableV2Selection.moreOfferedCourseSelections).toBeUndefined()
        expect(context.moreCoursesVisible).toBe(false)
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.moreOfferedCourseSelectionSnapshot).toBe('')
        expect(context.moreOfferedCourseSelectionSnapshotSelections).toEqual({})
    })

    it('keeps browser back inside the more courses flow after opening a course', async () => {
        const context = timetableV2Context({
            $route: {
                path: '/admin/students-timetables/timetable-v2/overview',
                query: {
                    mode: 'student',
                    step: 'timetable-calculation',
                    tt: '1',
                },
            },
            courseGroups: [
                { class_name: 'GS1-2A-PLOC', course: 'GS1', hour: 13, title: 'GS1', weekday: 4 },
            ],
            storedAdditionalCourseItems: [
                { code: 'GS1', hours: 4, label: 'GS1' },
            ],
            timetableCalculationVisible: true,
            timetableStartMode: 'student',
            timetableV2Step: 'timetable-calculation',
        })

        await TimetableV2.methods.openMoreCoursesCard.call(context)

        expect(context.$router.push).toHaveBeenLastCalledWith({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                mode: 'student',
                moreCourses: '1',
                step: 'timetable-calculation',
                tt: '1',
            },
        })

        context.$route.query = context.$router.push.mock.calls.at(-1)[0].query

        const moreCourse = context.moreCoursesCardItems[0]

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        expect(context.selectedMoreCourseKey).toBe(moreCourse.selectionKey)
        expect(context.$router.push).toHaveBeenLastCalledWith({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                mode: 'student',
                moreCourse: moreCourse.selectionKey,
                moreCourses: '1',
                step: 'timetable-calculation',
                tt: '1',
            },
        })

        context.$route.query = context.$router.push.mock.calls.at(-1)[0].query

        TimetableV2.methods.closeMoreCourseOffers.call(context)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(true)
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.$router.replace).toHaveBeenLastCalledWith({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                mode: 'student',
                moreCourses: '1',
                step: 'timetable-calculation',
                tt: '1',
            },
        })

        context.$route.query = context.$router.replace.mock.calls.at(-1)[0].query

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        expect(context.selectedMoreCourseKey).toBe(moreCourse.selectionKey)

        context.$route.query = context.$router.push.mock.calls.at(-1)[0].query
        context.$router.replace.mockClear()

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(true)
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.$router.replace).toHaveBeenLastCalledWith({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                mode: 'student',
                moreCourses: '1',
                step: 'timetable-calculation',
                tt: '1',
            },
        })

        context.$route.query = {
            mode: 'student',
            moreCourses: '1',
            step: 'timetable-calculation',
            tt: '1',
        }

        TimetableV2.methods.applyTimetableV2RouteFromRoute.call(context, { syncRoute: false })

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(true)
        expect(context.selectedMoreCourseKey).toBe('')
    })

    it('normalizes a route selected more course key before opening the course', () => {
        const context = timetableV2Context({
            $route: {
                path: '/admin/students-timetables/timetable-v2/overview',
                query: {
                    mode: 'student',
                    moreCourse: 'missing:BU2:',
                    moreCourses: '1',
                    step: 'timetable-calculation',
                    tt: '1',
                },
            },
            courseGroups: [
                { class_name: 'BU2-4A-KOW', course: 'BU2', hour: 1, title: 'BU2', weekday: 1 },
            ],
            storedMissingCourseCardItems: [
                { code: 'BU2', hours: 3, key: 'BU2', label: 'BU2' },
            ],
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.applyMoreCoursesRouteState.call(context)

        expect(context.selectedMoreCourseKey).toBe('missing:BU2')
        expect(context.selectedMoreCourseItem?.code).toBe('BU2')
        expect(context.moreCourseSelectedForAdding(context.selectedMoreCourseItem)).toBe(true)
    })

    it('deselects all offered courses for the selected more course', async () => {
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'M2-2Q-FUCH', course: 'M2', hour: 13, title: 'M2', weekday: 1 },
                { class_name: 'M2-2A-ALT', course: 'M2', hour: 13, title: 'M2', weekday: 2 },
            ],
            storedAdditionalCourseItems: [
                { code: 'M2', hours: 3, label: 'M2' },
            ],
        })

        await TimetableV2.methods.toggleMoreCoursesCard.call(context)

        const moreCourse = context.moreCoursesCardItems.find((course) => course.label === 'M2')

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        const offeredCourses = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)

        expect(offeredCourses).toHaveLength(2)
        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAnySelected.call(context, moreCourse)).toBe(true)

        TimetableV2.methods.deselectSelectedMoreCourseOfferedCourses.call(context)

        const savedState = context.saveStoredTimetableState.mock.calls.at(-1)[0]
        context.storedTimetableState = savedState

        expect(savedState.timetableV2Selection.moreOfferedCourseSelections).toEqual(Object.fromEntries(
            offeredCourses.map((course) => [course.selectionKey, false])
        ))
        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAllDeselected.call(context, moreCourse)).toBe(true)
    })

    it('does not mix ethics and religion offered courses', () => {
        const context = timetableV2Context({
            courseGroups: [
                {
                    class_name: 'ETH - 3 - 3CK - NIE',
                    course: 'ETH',
                    hour: 10,
                    module_code: 'ETH3',
                    title: 'ETH',
                    weekday: 3,
                },
                {
                    class_name: 'ETH - 3 - 5RU - HER',
                    course: 'ETH',
                    hour: 6,
                    module_code: 'ETH3',
                    title: 'ETH',
                    weekday: 5,
                },
                {
                    class_name: 'Rk - 3 - ENNS',
                    course: 'Rk',
                    hour: 8,
                    module_code: 'Rk3',
                    title: 'Rk',
                    weekday: 5,
                },
            ],
        })

        const ethicsOffers = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, {
            code: 'ETH3',
            hours: 3,
            label: 'ETH3',
            selectionKey: 'planned:ETH3',
        })
        const catholicReligionOffers = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, {
            code: 'Rk3',
            hours: 3,
            label: 'Rk3',
            selectionKey: 'planned:Rk3',
        })

        expect(ethicsOffers).toHaveLength(2)
        expect(ethicsOffers.map((course) => course.courseGroup.module_code)).toEqual(['ETH3', 'ETH3'])
        expect(ethicsOffers.map((course) => course.courseGroup.module_code)).not.toContain('Rk3')
        expect(catholicReligionOffers).toHaveLength(1)
        expect(catholicReligionOffers[0].courseGroup.module_code).toBe('Rk3')
    })

    it('only offers the selected student religion for generic religion courses', () => {
        const context = timetableV2Context({
            courseGroups: [
                {
                    class_name: 'Ris - 4 - EYG',
                    course: 'Ris',
                    hour: 3,
                    module_code: 'Ris4',
                    title: 'Ris',
                    weekday: 1,
                },
                {
                    class_name: 'Rk - 4 - ENNS',
                    course: 'Rk',
                    hour: 4,
                    module_code: 'Rk4',
                    title: 'Rk',
                    weekday: 2,
                },
            ],
            storedTimetableState: {
                timetableV2Selection: {
                    religion: 'Ris',
                },
            },
            storedTimetableStudentContext: {
                student: {
                    religion: 'islam. (IGGÖ)',
                },
            },
        })

        const religionOffers = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, {
            code: 'R4',
            hours: 1,
            label: 'R4',
            selectionKey: 'completed:R4',
        })

        expect(religionOffers).toHaveLength(1)
        expect(religionOffers.map((course) => course.courseGroup.module_code)).toEqual(['Ris4'])
        expect(religionOffers.map((course) => course.courseGroup.module_code)).not.toContain('Rk4')
    })

    it('marks impossible more courses red and opens their offers without allowing selection', () => {
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

        expect(context.selectedMoreCourseKey).toBe(moreCourse.selectionKey)

        const [offeredCourse] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
        expect(TimetableV2.methods.moreCourseOfferDisabled.call(context, offeredCourse, moreCourse)).toBe(true)

        const saveCallCount = context.saveStoredTimetableState.mock.calls.length
        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, offeredCourse, moreCourse)

        expect(context.saveStoredTimetableState).toHaveBeenCalledTimes(saveCallCount)
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
            context.moreCoursesCardItems
                .flatMap((course) => TimetableV2.methods.moreCourseAvailabilityPayloadCandidates.call(context, course, { includeOffers: true }))
                .map((course) => [course.availability_key, false])
        )

        expect(context.moreCoursesButtonUnavailable).toBe(true)

        TimetableV2.methods.moreCourseAvailabilityPayloadCandidates
            .call(context, context.moreCoursesCardItems[0], { includeOffers: true })
            .forEach((course) => {
                context.moreCourseAvailabilityByKey[course.availability_key] = true
            })

        expect(context.moreCoursesButtonUnavailable).toBe(false)
    })

    it('keeps the more courses card openable when every more course is impossible', () => {
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
        context.moreCourseAvailabilityByKey = Object.fromEntries(
            context.moreCoursesCardItems
                .flatMap((course) => TimetableV2.methods.moreCourseAvailabilityPayloadCandidates.call(context, course, { includeOffers: true }))
                .map((course) => [course.availability_key, false])
        )

        expect(context.moreCoursesButtonUnavailable).toBe(true)

        TimetableV2.methods.toggleMoreCoursesCard.call(context)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(true)
    })

    it('loads more course availability from the batch result', async () => {
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
            ],
            moreCourseAvailabilityRequestId: 1,
            requestMoreCourseAvailability: vi.fn((courses) => Promise.resolve({
                data: {
                    data: {
                        availability: {
                            [courses[0].selectionKey]: {
                                available: false,
                                valid_timetable_count: 0,
                            },
                        },
                    },
                },
            })),
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
        })
        const moreCourse = context.moreCoursesCardItems[0]
        const offeredCourse = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)[0]
        const offerAvailabilityKey = TimetableV2.methods.moreCourseOfferAvailabilityKey.call(context, moreCourse, offeredCourse)

        await TimetableV2.methods.loadMoreCourseAvailabilityForCourse.call(context, moreCourse, 1)

        expect(context.requestMoreCourseAvailability).toHaveBeenCalledWith([moreCourse], { includeOffers: true })
        expect(context.moreCourseAvailabilityByKey).toEqual({
            [moreCourse.selectionKey]: false,
            [offerAvailabilityKey]: false,
        })
    })

    it('keeps active options in the batch availability signature', async () => {
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'INF2-A', course: 'INF2', hour: 2, title: 'INF2', weekday: 2 },
            ],
            moreCourseAvailabilityRequestId: 1,
            requestMoreCourseAvailability: vi.fn((courses) => Promise.resolve({
                data: {
                    data: {
                        availability: {
                            [courses[0].selectionKey]: {
                                available: false,
                                valid_timetable_count: 0,
                            },
                        },
                    },
                },
            })),
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
            timetableMaxFreeDaysSelected: true,
        })
        const moreCourse = context.moreCoursesCardItems[0]
        const offeredCourse = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)[0]
        const offerAvailabilityKey = TimetableV2.methods.moreCourseOfferAvailabilityKey.call(context, moreCourse, offeredCourse)
        const availabilitySignature = TimetableV2.methods.moreCourseAvailabilityCurrentSignature.call(context)

        await TimetableV2.methods.loadMoreCourseAvailabilityForCourse.call(context, moreCourse, 1)

        expect(availabilitySignature).toContain('"maxFreeDaysSelected":true')
        expect(context.requestMoreCourseAvailability).toHaveBeenCalledWith([moreCourse], { includeOffers: true })
        expect(context.moreCourseAvailabilityByKey).toEqual({
            [moreCourse.selectionKey]: false,
            [offerAvailabilityKey]: false,
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
        const availabilityCandidates = TimetableV2.methods.moreCourseAvailabilityPayloadCandidates.call(context, moreCourse, { includeOffers: true })

        const loadingRequest = TimetableV2.methods.openMoreCoursesCard.call(context)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCourseAvailabilityLoading).toBe(true)
        expect(context.timetableCalculationProgressSource).toBe('availability')
        expect(context.timetableCalculationProgressValue).toBeGreaterThanOrEqual(0)
        expect(context.moreCourseAvailabilityByKey).toEqual({})
        expect(TimetableV2.methods.moreCourseDisabled.call(context, moreCourse)).toBe(true)

        resolveAvailabilityRequest({
            data: {
                data: {
                    availability: {
                        [moreCourse.selectionKey]: {
                            available: true,
                            valid_timetable_count: 1,
                        },
                    },
                },
            },
        })
        await loadingRequest

        expect(context.moreCourseAvailabilityLoading).toBe(false)
        expect(context.moreCourseAvailabilityByKey).toEqual(Object.fromEntries(
            availabilityCandidates.map((course) => [
                course.availability_key,
                course.availability_key === moreCourse.selectionKey,
            ]),
        ))
        expect(TimetableV2.methods.moreCourseDisabled.call(context, moreCourse)).toBe(false)
    })

    it('runs batched more course availability after calculating timetables and completes progress', async () => {
        const requestMoreCourseAvailability = vi.fn((courses) => Promise.resolve({
            data: {
                data: {
                    availability: {
                        [courses[0].selectionKey]: {
                            available: true,
                            valid_timetable_count: 2,
                        },
                    },
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
        const availabilityCandidates = TimetableV2.methods.moreCourseAvailabilityPayloadCandidates.call(context, moreCourse, { includeOffers: true })

        await TimetableV2.methods.calculateTimetables.call(context)
        await new Promise((resolve) => {
            setTimeout(resolve, 0)
        })

        expect(requestMoreCourseAvailability).toHaveBeenCalledWith([moreCourse], { includeOffers: true })
        expect(context.moreCourseAvailabilityLoading).toBe(false)
        expect(context.moreCourseAvailabilityByKey).toEqual(Object.fromEntries(
            availabilityCandidates.map((course) => [
                course.availability_key,
                course.availability_key === moreCourse.selectionKey,
            ]),
        ))
        expect(context.timetableCalculationProgressSource).toBe('availability')
        expect(context.timetableCalculationProgressValue).toBe(100)
        expect(context.timetableCalculationProgressLabel).toBe('100%')

        expect(requestMoreCourseAvailability).toHaveBeenCalledOnce()
    })

    it('loads timetable option counters in the background after calculating timetables', async () => {
        let resolveQualityCountersRequest: (value: unknown) => void = () => {}
        const qualityCountersRequest = new Promise((resolve) => {
            resolveQualityCountersRequest = resolve
        })
        const requestTimetableV2Calculation = vi.fn(() => Promise.resolve({
            data: {
                data: {
                    full_green_timetable_count: 5,
                    green_timetable_count: 0,
                    selected_timetable: { number: 1, type: 'full_green' },
                },
            },
        }))
        const requestTimetableV2QualityCounters = vi.fn(() => qualityCountersRequest)
        const context = timetableV2Context({
            calculateTimetables: TimetableV2.methods.calculateTimetables,
            ensureMoreCourseAvailability: vi.fn(() => Promise.resolve([])),
            requestTimetableV2Calculation,
            requestTimetableV2QualityCounters,
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        await TimetableV2.methods.calculateTimetables.call(context)

        expect(requestTimetableV2Calculation).toHaveBeenCalledWith(expect.objectContaining({
            includeQualityCounters: false,
        }))
        expect(context.ensureMoreCourseAvailability).toHaveBeenCalledOnce()
        expect(requestTimetableV2QualityCounters).toHaveBeenCalledOnce()
        expect(context.timetableCalculationLoading).toBe(false)
        expect(context.timetableQualityCountersLoading).toBe(true)
        expect(context.timetableCalculationResult.quality_counters).toBeUndefined()

        const qualityCounters = [
            {
                key: 'free_days',
                count: 3,
                best_value: 2,
                best_label: '2 freie Tage',
            },
        ]

        resolveQualityCountersRequest({
            data: {
                data: {
                    all_quality_criteria_count: 4,
                    quality_counters: qualityCounters,
                    selected_quality_criteria_count: 2,
                },
            },
        })
        await new Promise((resolve) => {
            setTimeout(resolve, 0)
        })

        expect(context.timetableQualityCountersLoading).toBe(false)
        expect(context.timetableCalculationResult.quality_counters).toBe(qualityCounters)
        expect(context.timetableCalculationResult.all_quality_criteria_count).toBe(4)
        expect(context.timetableCalculationResult.selected_quality_criteria_count).toBe(2)
    })

    it('does not refresh more courses or option counters when only changing timetable number', async () => {
        const previousQualityCounters = [
            {
                key: 'free_days',
                count: 7,
                best_value: 2,
                best_label: '2 freie Tage',
            },
            {
                key: 'starts_from_period_10',
                count: 4,
            },
        ]
        const requestMoreCourseAvailability = vi.fn()
        const requestTimetableV2Calculation = vi.fn(() => Promise.resolve({
            data: {
                data: {
                    full_green_timetable_count: 5,
                    green_timetable_count: 0,
                    selected_timetable: { number: 2, type: 'full_green' },
                },
            },
        }))
        const context = timetableV2Context({
            calculateTimetables: TimetableV2.methods.calculateTimetables,
            moreCourseAvailabilityByKey: {
                'additional:INF2': true,
            },
            moreCourseAvailabilitySignature: 'same-selection',
            requestMoreCourseAvailability,
            requestTimetableV2Calculation,
            timetableCalculationResult: {
                full_green_timetable_count: 5,
                no_saturday_timetable_count: 3,
                quality_counters: previousQualityCounters,
                selected_timetable: { number: 1, type: 'full_green' },
            },
            timetableCalculationSelectedNumber: 2,
            timetableV2Step: 'timetable-calculation',
        })

        expect(context.timetableCalculationLoadingLabel).toBe('Die Stundenpläne werden berechnet.')
        expect(context.timetableCalculationLoadingIcon).toBe('mdi-calculator-variant-outline')

        await TimetableV2.methods.calculateTimetables.call(context, { refreshAuxiliary: false })

        expect(context.timetableCalculationLoadingMode).toBe('timetable')
        expect(context.timetableCalculationLoadingLabel).toBe('Der Stundenplan wird geladen.')
        expect(context.timetableCalculationLoadingIcon).toBe('mdi-calendar-clock-outline')
        expect(requestTimetableV2Calculation).toHaveBeenCalledWith(expect.objectContaining({
            includeQualityCounters: false,
            selectedTimetableNumber: 2,
        }))
        expect(requestMoreCourseAvailability).not.toHaveBeenCalled()
        expect(context.moreCourseAvailabilityByKey).toEqual({
            'additional:INF2': true,
        })
        expect(context.moreCourseAvailabilitySignature).toBe('same-selection')
        expect(context.timetableCalculationResult.quality_counters).toBe(previousQualityCounters)
        expect(context.timetableCalculationResult.no_saturday_timetable_count).toBe(3)
    })

    it('shows determinate calculation progress for more course and option calculations', async () => {
        vi.useFakeTimers()

        try {
            const requestTimetableV2Calculation = vi.fn().mockResolvedValue({
                data: {
                    data: {
                        selected_timetable: {
                            number: 1,
                            type: 'full_green',
                        },
                    },
                },
            })
            const context = timetableV2Context({
                calculateTimetables: TimetableV2.methods.calculateTimetables,
                requestTimetableV2Calculation,
                timetableCalculationVisible: true,
                timetableV2Step: 'timetable-calculation',
            })

            expect(context.timetableCalculationProgressVisible).toBe(false)

            await TimetableV2.methods.calculateTimetables.call(context, { progressContext: 'more-courses' })

            expect(requestTimetableV2Calculation).toHaveBeenCalledWith(expect.not.objectContaining({
                progressContext: 'more-courses',
            }))
            expect(context.timetableCalculationProgressSource).toBe('more-courses')
            expect(context.timetableCalculationProgressValue).toBeGreaterThan(0)
            expect(context.timetableCalculationProgressVisible).toBe(true)
            expect(context.timetableCalculationProgressValue).toBe(100)
            expect(context.timetableCalculationProgressLabel).toBe('100%')

            TimetableV2.methods.clearTimetableCalculationProgressTimers.call(context)
            context.timetableCalculationProgress = 0
            context.timetableCalculationProgressSource = ''

            await TimetableV2.methods.calculateTimetables.call(context, { progressContext: 'options' })

            expect(context.timetableCalculationProgressSource).toBe('options')

            TimetableV2.methods.clearTimetableCalculationProgressTimers.call(context)
        } finally {
            vi.useRealTimers()
        }
    })

    it('waits to complete calculation progress while background more course availability checks continue', async () => {
        vi.useFakeTimers()

        try {
            const context = timetableV2Context({
                moreCourseAvailabilityLoading: true,
                timetableCalculationProgress: 95,
                timetableCalculationProgressSource: 'more-courses',
                timetableCalculationVisible: true,
                timetableV2Step: 'timetable-calculation',
            })

            TimetableV2.methods.completeTimetableCalculationProgress.call(context)

            expect(context.timetableCalculationProgressCompletionPending).toBe(true)
            expect(context.timetableCalculationProgressValue).toBe(95)
            expect(context.timetableCalculationProgressLabel).toBe('Fast fertig')

            context.moreCourseAvailabilityLoading = false
            TimetableV2.methods.completeTimetableCalculationProgress.call(context)

            expect(context.timetableCalculationProgressCompletionPending).toBe(false)
            expect(context.timetableCalculationProgressValue).toBe(100)
            expect(context.timetableCalculationProgressLabel).toBe('100%')

            TimetableV2.methods.clearTimetableCalculationProgressTimers.call(context)
        } finally {
            vi.useRealTimers()
        }
    })

    it('waits to complete calculation progress until option counters finish loading', async () => {
        vi.useFakeTimers()

        try {
            const context = timetableV2Context({
                timetableCalculationProgress: 95,
                timetableCalculationProgressSource: 'options',
                timetableCalculationVisible: true,
                timetableQualityCountersLoading: true,
                timetableV2Step: 'timetable-calculation',
            })

            TimetableV2.methods.completeTimetableCalculationProgress.call(context)

            expect(context.timetableCalculationProgressCompletionPending).toBe(true)
            expect(context.timetableCalculationProgressValue).toBe(95)
            expect(context.timetableCalculationProgressVisible).toBe(true)

            context.timetableQualityCountersLoading = false
            TimetableV2.methods.completeTimetableCalculationProgress.call(context)

            expect(context.timetableCalculationProgressCompletionPending).toBe(false)
            expect(context.timetableCalculationProgressValue).toBe(100)
            expect(context.timetableCalculationProgressLabel).toBe('100%')

            TimetableV2.methods.clearTimetableCalculationProgressTimers.call(context)
        } finally {
            vi.useRealTimers()
        }
    })

    it('keeps finalizing progress moving instead of waiting at 95 percent', () => {
        vi.useFakeTimers()

        try {
            const context = timetableV2Context({
                timetableCalculationLoading: true,
                timetableCalculationProgress: 95,
                timetableCalculationProgressSource: 'more-courses',
                timetableCalculationVisible: true,
                timetableV2Step: 'timetable-calculation',
            })

            TimetableV2.methods.startTimetableCalculationProgress.call(context, 'more-courses')
            context.timetableCalculationProgress = 95

            vi.advanceTimersByTime(1400)

            expect(context.timetableCalculationProgressValue).toBeGreaterThan(95)
            expect(context.timetableCalculationProgressValue).toBeLessThan(100)
            expect(context.timetableCalculationProgressLabel).toBe('Fast fertig')

            TimetableV2.methods.clearTimetableCalculationProgressTimers.call(context)
        } finally {
            vi.useRealTimers()
        }
    })

    it('waits to complete availability progress until more course availability checks finish', async () => {
        vi.useFakeTimers()

        try {
            const context = timetableV2Context({
                moreCourseAvailabilityLoading: true,
                timetableCalculationProgress: 95,
                timetableCalculationProgressSource: 'availability',
                timetableCalculationVisible: true,
                timetableV2Step: 'timetable-calculation',
            })

            TimetableV2.methods.completeTimetableCalculationProgress.call(context)

            expect(context.timetableCalculationProgressCompletionPending).toBe(true)
            expect(context.timetableCalculationProgressValue).toBe(95)

            context.moreCourseAvailabilityLoading = false
            TimetableV2.methods.completeTimetableCalculationProgress.call(context)

            expect(context.timetableCalculationProgressCompletionPending).toBe(false)
            expect(context.timetableCalculationProgressValue).toBe(100)
            expect(context.timetableCalculationProgressLabel).toBe('100%')

            TimetableV2.methods.clearTimetableCalculationProgressTimers.call(context)
        } finally {
            vi.useRealTimers()
        }
    })

    it('waits to complete calculation progress until conflict resolution actions are ready', async () => {
        vi.useFakeTimers()

        try {
            const context = timetableV2Context({
                conflictResolutionRecommendationLoading: true,
                moreCourseAvailabilityLoading: false,
                timetableCalculationProgress: 95,
                timetableCalculationProgressSource: 'more-courses',
                timetableCalculationVisible: true,
                timetableV2Step: 'timetable-calculation',
            })

            TimetableV2.methods.completeTimetableCalculationProgress.call(context)

            expect(context.timetableCalculationProgressCompletionPending).toBe(true)
            expect(context.timetableCalculationProgressValue).toBe(95)

            context.conflictResolutionRecommendationLoading = false
            TimetableV2.methods.completeTimetableCalculationProgress.call(context)

            expect(context.timetableCalculationProgressCompletionPending).toBe(false)
            expect(context.timetableCalculationProgressValue).toBe(100)
            expect(context.timetableCalculationProgressLabel).toBe('100%')

            TimetableV2.methods.clearTimetableCalculationProgressTimers.call(context)
        } finally {
            vi.useRealTimers()
        }
    })

    it('keeps timetable calculation loading until conflict resolution recommendations finish', async () => {
        let resolveRecommendations
        const recommendationPromise = new Promise((resolve) => {
            resolveRecommendations = resolve
        })
        const context = timetableV2Context({
            ensureMoreCourseAvailability: vi.fn(() => Promise.resolve({})),
            ensureConflictResolutionRecommendations: vi.fn(() => recommendationPromise),
            requestTimetableV2Calculation: vi.fn(() => Promise.resolve({
                data: {
                    data: {
                        conflict_timetable_count: 1,
                        selected_timetable: {
                            number: 1,
                            type: 'conflict',
                        },
                    },
                },
            })),
            selectedTimetableV2CombinedNumberFromResult: () => 1,
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        const calculationPromise = TimetableV2.methods.calculateTimetables.call(context, {
            progressContext: 'more-courses',
        })

        await Promise.resolve()
        await Promise.resolve()

        expect(context.ensureConflictResolutionRecommendations).toHaveBeenCalledOnce()
        expect(context.timetableCalculationLoading).toBe(true)
        expect(context.timetableCalculationProgressValue).toBeLessThan(100)

        resolveRecommendations({})
        await calculationPromise

        expect(context.timetableCalculationLoading).toBe(false)
        expect(context.timetableCalculationProgressValue).toBe(100)

        TimetableV2.methods.clearTimetableCalculationProgressTimers.call(context)
    })

    it('loads conflict resolution recommendations when moving to another conflict timetable', async () => {
        let resolveRecommendations
        const recommendationPromise = new Promise((resolve) => {
            resolveRecommendations = resolve
        })
        const context = timetableV2Context({
            ensureMoreCourseAvailability: vi.fn(() => Promise.resolve({})),
            ensureConflictResolutionRecommendations: vi.fn(() => recommendationPromise),
            requestTimetableV2Calculation: vi.fn(() => Promise.resolve({
                data: {
                    data: {
                        conflict_timetable_count: 2,
                        selected_timetable: {
                            number: 2,
                            type: 'conflict',
                        },
                    },
                },
            })),
            selectedTimetableV2CombinedNumberFromResult: () => 2,
            timetableCalculationSelectedNumber: 2,
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        const calculationPromise = TimetableV2.methods.calculateTimetables.call(context, {
            refreshAuxiliary: false,
        })

        await Promise.resolve()
        await Promise.resolve()

        expect(context.ensureMoreCourseAvailability).not.toHaveBeenCalled()
        expect(context.ensureConflictResolutionRecommendations).toHaveBeenCalledOnce()
        expect(context.timetableCalculationLoading).toBe(true)

        resolveRecommendations({})
        await calculationPromise

        expect(context.timetableCalculationLoading).toBe(false)
    })

    it('uses cached timetable results when returning to an already loaded timetable number', async () => {
        const requestTimetableV2Calculation = vi.fn()
            .mockResolvedValueOnce({
                data: {
                    data: {
                        full_green_timetable_count: 2,
                        green_timetable_count: 0,
                        selected_timetable: {
                            number: 1,
                            slots: {
                                '1-1': { course: 'D1' },
                            },
                            type: 'full_green',
                        },
                    },
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: {
                        full_green_timetable_count: 2,
                        green_timetable_count: 0,
                        selected_timetable: {
                            number: 2,
                            slots: {
                                '1-1': { course: 'M1' },
                            },
                            type: 'full_green',
                        },
                    },
                },
            })
        const context = timetableV2Context({
            calculateTimetables: TimetableV2.methods.calculateTimetables,
            requestTimetableV2Calculation,
            timetableCalculationSelectedNumber: 1,
            timetableV2Step: 'timetable-calculation',
        })

        await TimetableV2.methods.calculateTimetables.call(context)

        TimetableV2.methods.setSelectedTimetableV2Number.call(context, 2, { syncRoute: false })
        await TimetableV2.methods.calculateTimetables.call(context, { refreshAuxiliary: false })

        TimetableV2.methods.setSelectedTimetableV2Number.call(context, 1, { syncRoute: false })
        await TimetableV2.methods.calculateTimetables.call(context, { refreshAuxiliary: false })

        expect(requestTimetableV2Calculation).toHaveBeenCalledTimes(2)
        expect(context.timetableCalculationSelectedNumber).toBe(1)
        expect(context.timetableCalculationResult.selected_timetable.slots).toEqual({
            '1-1': { course: 'D1' },
        })
    })

    it('reuses completed more course availability when opening the card', async () => {
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
        const availabilityCandidates = TimetableV2.methods.moreCourseAvailabilityPayloadCandidates.call(context, moreCourse, { includeOffers: true })
        context.moreCourseAvailabilitySignature = TimetableV2.methods.moreCourseAvailabilityCurrentSignature.call(context)
        context.moreCourseAvailabilityByKey = Object.fromEntries(
            availabilityCandidates.map((course) => [course.availability_key, true])
        )

        await TimetableV2.methods.openMoreCoursesCard.call(context)

        expect(requestMoreCourseAvailability).not.toHaveBeenCalled()
        expect(context.moreCourseAvailabilityLoading).toBe(false)
        expect(context.moreCourseAvailabilityByKey).toEqual(Object.fromEntries(
            availabilityCandidates.map((course) => [course.availability_key, true])
        ))
    })

    it('honors unavailable availability for removed selected courses', async () => {
        let context: ReturnType<typeof timetableV2Context>
        const requestMoreCourseAvailability = vi.fn(() => Promise.resolve({
            data: {
                data: {
                    availability: {},
                },
            },
        }))
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            courseGroups: [
                { class_name: 'M1-A', course: 'M1', hour: 1, title: 'M1', weekday: 1 },
                { class_name: 'GW2-A', course: 'GW2', hour: 2, title: 'GW2', weekday: 2 },
                { class_name: 'GS1-A', course: 'GS1', hour: 3, title: 'GS1', weekday: 3 },
            ],
            requestMoreCourseAvailability,
            saveStoredTimetableState,
            storedAdditionalCourseItems: [
                { code: 'GS1', hours: 4, key: 'GS1', label: 'GS1' },
            ],
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, key: 'D1', label: 'D1' },
                { code: 'M1', hours: 4, key: 'M1', label: 'M1' },
                { code: 'GW2', hours: 4, key: 'GW2', label: 'GW2' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'planned:GW2': false,
                    },
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationVisible: true,
            timetableMaxFreeDaysSelected: true,
        })
        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(
            context,
            context.storedPlannedCourseItems[1],
            'planned',
        )

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)

        const removedMoreCourse = context.moreCoursesCardItems.find((course) => course.key === 'M1')
        const unavailableMoreCourse = context.moreCoursesCardItems.find((course) => course.key === 'GS1')
        context.moreCourseAvailabilitySignature = TimetableV2.methods.moreCourseAvailabilityCurrentSignature.call(context)
        context.moreCourseAvailabilityByKey = Object.fromEntries(
            context.moreCoursesCardItems
                .flatMap((course) => TimetableV2.methods.moreCourseAvailabilityPayloadCandidates.call(context, course, { includeOffers: true }))
                .map((course) => [course.availability_key, false])
        )
        context.restorableMoreCourseAvailabilitySignatures = {}

        expect(TimetableV2.methods.moreCourseRestorable.call(context, removedMoreCourse)).toBe(false)
        expect(TimetableV2.methods.moreCourseUnavailable.call(context, removedMoreCourse)).toBe(true)
        expect(TimetableV2.methods.moreCourseUnavailable.call(context, unavailableMoreCourse)).toBe(true)
        expect(context.moreCoursesButtonUnavailable).toBe(true)

        context.moreCourseAvailabilityRequestId = 1

        await TimetableV2.methods.loadMoreCourseAvailabilityForCourse.call(context, removedMoreCourse, 1)

        expect(requestMoreCourseAvailability).toHaveBeenCalledWith([removedMoreCourse], { includeOffers: true })
        expect(context.moreCourseAvailabilityByKey[removedMoreCourse.selectionKey]).toBe(false)
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

    it('moves a removed selected course into pending actions and restores it on cancel', () => {
        let context: ReturnType<typeof timetableV2Context>
        const calculateTimetables = vi.fn()
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables,
            saveStoredTimetableState,
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, key: 'D1', label: 'D1' },
                { code: 'GW1', hours: 4, key: 'GW1', label: 'GW1' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'planned:D1': true,
                        'planned:GW1': true,
                    },
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })
        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(context, context.storedPlannedCourseItems[0], 'planned')

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'planned:GW1': true,
        })
        expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['GWB1'])
        expect(context.moreCoursesCardItems.map((course) => course.label)).toContain('D1')
        expect(calculateTimetables).not.toHaveBeenCalled()

        TimetableV2.methods.cancelMoreCoursesCard.call(context)

        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'planned:D1': true,
            'planned:GW1': true,
        })
        expect(context.moreCoursesVisible).toBe(false)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.moreCoursesCardItems.map((course) => course.label)).not.toContain('D1')
    })

    it('stages removal of a default selected semester course and closes the picker after applying', async () => {
        let context: ReturnType<typeof timetableV2Context>
        const calculateTimetables = vi.fn(() => {
            context.moreCoursesVisible = true
            context.moreCoursesCardVisible = true

            return Promise.resolve({})
        })
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            $route: {
                path: '/admin/students-timetables/timetable-v2/overview',
                query: {
                    mode: 'student',
                    moreCourses: '1',
                    step: 'timetable-calculation',
                    tt: '1',
                },
            },
            calculateTimetables,
            saveStoredTimetableState,
            storedSemesterCourseItems: [
                { hours: 3, key: 'subject-row-m4', label: 'M4' },
                { code: 'D4', hours: 4, key: 'D4', label: 'D4' },
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
            timetableStartMode: 'student',
            timetableV2Step: 'timetable-calculation',
        })
        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(context, context.storedSemesterCourseItems[0], 'semester')

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['D4'])
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'semester:subject-row-m4': false,
            'semester:M4': false,
        })
        expect(TimetableV2.methods.courseSelectedBySelections.call(
            context,
            { code: 'M4', hours: 3, key: 'M4', label: 'M4' },
            'semester',
            context.storedTimetableState.timetableV2Selection.courseSelections,
        )).toBe(false)
        expect(context.moreCoursesSelectionChanged).toBe(true)
        expect(context.moreCoursesButtonUnavailable).toBe(false)
        expect(context.timetableCalculationResetAvailable).toBe(true)

        TimetableV2.methods.toggleMoreCoursesCard.call(context)

        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(true)
        expect(context.moreCoursesSelectionChanged).toBe(true)

        await TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(context.moreCoursesVisible).toBe(false)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['D4'])
        expect(context.$router.replace).toHaveBeenLastCalledWith({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                mode: 'student',
                step: 'timetable-calculation',
                tt: '1',
            },
        })
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('hides a removed selected course immediately while the state save is still pending', () => {
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            saveStoredTimetableState,
            storedSemesterCourseItems: [
                { hours: 3, key: 'subject-row-m4', label: 'M4' },
                { code: 'D4', hours: 4, key: 'D4', label: 'D4' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
            timetableCalculationVisible: true,
            timetableStartMode: 'student',
            timetableV2Step: 'timetable-calculation',
        })
        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(context, context.storedSemesterCourseItems[0], 'semester')

        expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['D4', 'M4'])

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)

        expect(saveStoredTimetableState).toHaveBeenCalledOnce()
        expect(context.pendingRemovedSelectedCourseKeys).toEqual({
            'semester:M4': true,
            'semester:subject-row-m4': true,
        })
        expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['D4'])
        expect(context.moreCoursesSelectionChanged).toBe(true)
    })

    it('uses the course code instead of an internal subject-row key for selected semester courses', () => {
        const context = timetableV2Context({
            storedSemesterCourseItems: [
                { hours: 3, key: 'subject-row-m4', label: 'M4' },
                { code: 'D4', hours: 4, key: 'D4', label: 'D4' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
            timetableCalculationVisible: true,
            timetableStartMode: 'student',
            timetableV2Step: 'timetable-calculation',
        })

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['D4', 'M4'])
        expect(payload.selected_course_keys).toEqual(['D4', 'M4'])
        expect(payload.selected_course_keys).not.toContain('subject-row-m4')
    })

    it('keeps a removed default selected semester course out of the recalculation payload', async () => {
        let context: ReturnType<typeof timetableV2Context>
        let requestPayload
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables: TimetableV2.methods.calculateTimetables,
            requestTimetableV2Calculation: vi.fn((options = {}) => {
                requestPayload = TimetableV2.methods.timetableV2CalculationPayload.call(context, options)

                return Promise.resolve({
                    data: {
                        data: {
                            full_green_timetable_count: 1,
                            green_timetable_count: 0,
                            selected_timetable: { number: 1, type: 'full_green' },
                        },
                    },
                })
            }),
            saveStoredTimetableState,
            storedSemesterCourseItems: [
                { hours: 3, key: 'subject-row-m4', label: 'M4' },
                { code: 'D4', hours: 4, key: 'D4', label: 'D4' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationResult: {
                full_green_timetable_count: 1,
                green_timetable_count: 0,
                selected_timetable: { number: 1, type: 'full_green' },
            },
            timetableCalculationVisible: true,
            timetableStartMode: 'student',
            timetableV2Step: 'timetable-calculation',
        })
        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(context, context.storedSemesterCourseItems[0], 'semester')

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)
        await TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['D4'])
        expect(requestPayload.selected_course_keys).toEqual(['D4'])
        expect(requestPayload.selected_course_keys).not.toContain('M4')
        expect(context.timetableCalculationResult.full_green_timetable_count).toBe(1)
    })

    it('applies removed selected courses by recalculating the timetable', () => {
        let context: ReturnType<typeof timetableV2Context>
        const calculateTimetables = vi.fn()
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables,
            moreCoursesVisible: false,
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

    it('confirms selected course removal on the adopted timetable page without recalculating', () => {
        let context: ReturnType<typeof timetableV2Context>
        const calculateTimetables = vi.fn()
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables,
            moreCoursesVisible: false,
            saveStoredTimetableState,
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, key: 'D1', label: 'D1' },
                { code: 'M1', hours: 4, key: 'M1', label: 'M1' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'planned:D1': true,
                        'planned:M1': true,
                    },
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '1-1': {
                            code: 'D1',
                            conflicts: [
                                { code: 'M1', sourceLabel: 'M1-A' },
                            ],
                            sameSlotEntries: [
                                { code: 'M1', sourceLabel: 'M1-B' },
                            ],
                            sourceLabel: 'D1-A',
                        },
                        '2-1': {
                            code: 'M1',
                            conflicts: [
                                { code: 'D1', sourceLabel: 'D1-B' },
                            ],
                            sourceLabel: 'M1-A',
                        },
                    },
                },
            },
            timetableV2Step: 'timetable-adoption',
        })
        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(context, context.storedPlannedCourseItems[0], 'planned')

        expect(context.selectedCourseItemsDisabled).toBe(false)
        expect(context.selectedCourseItemsDeletable).toBe(true)
        expect(context.selectedTimetableSummaryChipsDisabled).toBe(true)

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)

        expect(context.adoptedTimetableCourseRemovalPendingVisible).toBe(true)
        expect(context.moreCoursesVisible).toBe(true)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'planned:D1': true,
            'planned:M1': true,
        })
        expect(context.adoptedTimetableSelectionSnapshot.courseSelections).toEqual({
            'planned:M1': true,
        })
        expect(context.adoptedTimetableCalculationResult.selected_timetable.slots['1-1']).toBeDefined()
        expect(context.adoptedTimetableCalculationResult.selected_timetable.slots['2-1'].conflicts).toHaveLength(1)

        TimetableV2.methods.applyAdoptedTimetableCourseRemoval.call(context)

        expect(context.adoptedTimetableCalculationResult.selected_timetable.slots['1-1']).toBeUndefined()
        expect(context.adoptedTimetableCalculationResult.selected_timetable.slots['2-1']).toEqual({
            code: 'M1',
            sourceLabel: 'M1-A',
        })
        expect(context.adoptedTimetableSelectionSnapshot.courseSelections).toEqual({
            'planned:M1': true,
        })
        expect(context.moreCoursesVisible).toBe(false)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(calculateTimetables).not.toHaveBeenCalled()
    })

    it('keeps the calculation course selection unchanged when editing an adopted timetable', () => {
        let context: ReturnType<typeof timetableV2Context>
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            saveStoredTimetableState,
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, key: 'D1', label: 'D1' },
                { code: 'M1', hours: 4, key: 'M1', label: 'M1' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'planned:D1': true,
                        'planned:M1': true,
                    },
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '1-1': { code: 'D1', sourceLabel: 'D1-A' },
                        '2-1': { code: 'M1', sourceLabel: 'M1-A' },
                    },
                },
            },
            timetableCalculationSelectedNumber: 1,
            timetableMaxFreeDaysDraftSelected: true,
            timetableMaxFreeDaysSelected: true,
            timetableNoDistanceLearningDraftSelected: true,
            timetableNoDistanceLearningSelected: true,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.adoptCurrentTimetableV2Result.call(context)

        expect(context.adoptedTimetableCalculationOptionsSnapshot).toEqual({
            maxFreeDays: true,
            noDistanceLearning: true,
            noSaturday: false,
            startsFromPeriod10: false,
        })

        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(context, context.storedPlannedCourseItems[0], 'planned')

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)
        TimetableV2.methods.applyAdoptedTimetableCourseRemoval.call(context)
        context.timetableMaxFreeDaysDraftSelected = false
        context.timetableMaxFreeDaysSelected = false
        context.timetableNoDistanceLearningDraftSelected = false
        context.timetableNoDistanceLearningSelected = false

        expect(context.selectedCourseItems.map((course) => course.code)).toEqual(['M1'])
        expect(context.adoptedTimetableSelectionSnapshot.courseSelections).toEqual({
            'planned:M1': true,
        })
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'planned:D1': true,
            'planned:M1': true,
        })

        TimetableV2.methods.backToTimetableCalculation.call(context)

        expect(context.timetableV2Step).toBe('timetable-calculation')
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'planned:D1': true,
            'planned:M1': true,
        })
        expect(context.storedTimetableState.timetableV2Options).toEqual({
            maxFreeDays: true,
            noDistanceLearning: true,
            noSaturday: false,
            startsFromPeriod10: false,
        })
        expect(context.timetableMaxFreeDaysDraftSelected).toBe(true)
        expect(context.timetableMaxFreeDaysSelected).toBe(true)
        expect(context.timetableNoDistanceLearningDraftSelected).toBe(true)
        expect(context.timetableNoDistanceLearningSelected).toBe(true)
        expect(context.selectedCourseItems.map((course) => course.code)).toEqual(['D1', 'M1'])

        TimetableV2.methods.adoptCurrentTimetableV2Result.call(context)

        expect(context.adoptedTimetableSelectionSnapshot.courseSelections).toEqual({
            'planned:D1': true,
            'planned:M1': true,
        })
        expect(context.selectedCourseItems.map((course) => course.code)).toEqual(['D1', 'M1'])
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

    it('keeps previously deselected offers deselected when a removed course is added again', () => {
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
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationVisible: true,
        })

        const selectedCourse = TimetableV2.methods.selectedCourseListItem.call(context, context.storedAdditionalCourseItems[0], 'additional')
        const [selectedOffer, deselectedOffer] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, selectedCourse)
        context.storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                courseSelections: {
                    'additional:INF2': true,
                },
                offeredCourseSelections: {
                    [deselectedOffer.selectionKey]: false,
                },
            },
            transferredStudentContext: null,
        }

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedCourse)

        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'INF2')
        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, selectedOffer, moreCourse)).toBe(true)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, deselectedOffer, moreCourse)).toBe(false)

        TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'additional:INF2': true,
        })
        expect(context.storedTimetableState.timetableV2Selection.offeredCourseSelections).toEqual({
            [deselectedOffer.selectionKey]: false,
        })
        expect(context.storedTimetableState.timetableV2Selection.moreOfferedCourseSelections).toBeUndefined()
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('shows an applied negative more course in the selected courses list', () => {
        let context: ReturnType<typeof timetableV2Context>
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables: vi.fn(),
            courseGroups: [
                { class_name: 'BU2-4A-KOW', course: 'BU2', hour: 1, title: 'BU2', weekday: 1 },
            ],
            saveStoredTimetableState,
            storedMissingCourseCardItems: [
                { code: 'BU2', hours: 3, key: 'BU2', label: 'BU2' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'missing:BU2': false,
                    },
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationVisible: true,
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'BU2')

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)
        TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'missing:BU2': true,
        })
        expect(context.selectedMissingCourseCardItems.map((course) => course.code)).toEqual(['BU2'])
        expect(context.selectedCourseItems.map((course) => course.code)).toContain('BU2')
    })

    it('canonicalizes an applied negative more course key before rendering selected courses', () => {
        let context: ReturnType<typeof timetableV2Context>
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            courseGroups: [
                { class_name: 'BU2-4A-KOW', course: 'BU2', hour: 1, title: 'BU2', weekday: 1 },
            ],
            saveStoredTimetableState,
            storedMissingCourseCardItems: [
                { code: 'BU2', hours: 3, key: 'BU2', label: 'BU2' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'missing:BU2': false,
                    },
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
        })
        const moreCourse = {
            ...context.moreCoursesCardItems.find((course) => course.key === 'BU2'),
            selectionKey: 'missing:BU2:',
        }
        const courseSelections = { ...context.courseSelectionOverrides }

        TimetableV2.methods.promoteSelectedMoreCourse.call(context, moreCourse, {
            courseSelections,
            offeredCourseSelections: {},
            moreOfferedCourseSelections: {},
        })
        TimetableV2.methods.saveMoreCoursesSelectionState.call(context, {
            courseSelections,
            offeredCourseSelections: {},
            moreOfferedCourseSelections: {},
        })

        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toEqual({
            'missing:BU2': true,
        })
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).not.toHaveProperty('missing:BU2:')
        expect(context.selectedCourseItems.map((course) => course.code)).toContain('BU2')
    })

    it('keeps an explicitly selected negative course visible when no offered courses are matched', () => {
        const context = timetableV2Context({
            courseGroupsLoaded: true,
            storedMissingCourseCardItems: [
                { code: 'BU2', hours: 3, key: 'BU2', label: 'BU2' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'missing:BU2': true,
                        'missing:BU2:': false,
                    },
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
        })

        expect(TimetableV2.methods.courseItemUnavailable.call(context, context.storedMissingCourseCardItems[0], 'missing')).toBe(true)
        expect(TimetableV2.methods.courseSelectionExplicitValue.call(context, context.storedMissingCourseCardItems[0], 'missing', {
            'missing:BU2': true,
            'missing:BU2:': false,
        })).toBe(true)
        expect(context.selectedCourseItems.map((course) => course.code)).toContain('BU2')
        expect(context.moreCoursesCardItems.map((course) => course.code)).not.toContain('BU2')
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
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'planned:D1': true,
                    },
                },
                transferredStudentContext: null,
            },
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

    it('does not infer the wirtschaftskundlich branch from visited INF1 courses', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['INF1']))).toBe('')
    })

    it('infers the wirtschaftskundlich branch from visited INF2 or INF3 courses', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['INF2']))).toBe('wirtschaftskundlich')
        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['INF3']))).toBe('wirtschaftskundlich')
    })

    it('infers the wirtschaftskundlich branch from failed visited INF2 courses', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.timetableV2SelectionWithCourseDefaults.call(context, {}, {
            completed: [
                { code: 'F1', grade: '4' },
            ],
            failed: [
                { code: 'INF2', grade: '5' },
            ],
        })).toMatchObject({
            branch: 'wirtschaftskundlich',
            language: 'F',
        })
    })

    it('infers the wirtschaftskundlich branch from visited economics aliases', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['OEKO1']))).toBe('wirtschaftskundlich')
        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['RW']))).toBe('wirtschaftskundlich')
    })

    it('infers the gymnasial branch from visited language courses starting at module 6', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['L5']))).toBe('')
        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['L6']))).toBe('gymnasial')
        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['S7']))).toBe('gymnasial')
    })

    it('does not infer the gymnasial branch from lower language modules when only INF1 is completed', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['INF1', 'L1', 'L2']))).toBe('')
        expect(TimetableV2.methods.timetableV2SelectionWithCourseDefaults.call(context, {}, [
            { code: 'INF1' },
            { code: 'L1' },
            { code: 'L2' },
        ])).toMatchObject({
            language: 'L',
        })
        expect(TimetableV2.methods.timetableV2SelectionWithCourseDefaults.call(context, {}, [
            { code: 'INF1' },
            { code: 'L1' },
            { code: 'L2' },
        ])).not.toHaveProperty('branch')
    })

    it('preselects Ris from an islam student religion before generic completed religion courses', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    religion: 'islam. (IGGÖ)',
                },
            },
        })

        expect(TimetableV2.methods.selectedStudentReligionDefault.call(context)).toBe('Ris')
        expect(TimetableV2.methods.timetableV2SelectionWithCourseDefaults.call(context, {}, [
            { code: 'R3' },
        ])).toMatchObject({
            religion: 'Ris',
        })
        expect(TimetableV2.methods.timetableV2SelectionWithCourseDefaults.call(context, {
            religion: 'ETH',
        }, [
            { code: 'R3' },
        ])).toMatchObject({
            religion: 'ETH',
        })
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

    it('opens the adopted timetable page with a frozen copy of the selected timetable', () => {
        const context = timetableV2Context({
            timetableV2Step: 'timetable-calculation',
            timetableCalculationSelectedNumber: 3,
            timetableCalculationResult: {
                full_green_timetable_count: 4,
                green_timetable_count: 0,
                selected_timetable: {
                    number: 3,
                    slots: {
                        '1-1': {
                            code: 'D1',
                            courseGroup: {
                                class_name: 'D1-A',
                            },
                            hour: 1,
                            weekday: 1,
                        },
                    },
                    type: 'full_green',
                },
            },
            storedTimetableState: {
                timetableV2Selection: {
                    language: 'E',
                    semester: 1,
                },
                transferredStudentContext: {
                    student: {
                        label: '1C · PABINGER Elena · Semester 1',
                    },
                },
            },
            storedTimetableStudentContext: {
                student: {
                    label: '1C · PABINGER Elena · Semester 1',
                },
            },
        })

        expect(context.adoptSelectedTimetableV2ButtonLabel).toBe('Stundenplan Nr. 3 übernehmen')

        TimetableV2.methods.adoptCurrentTimetableV2Result.call(context)

        context.timetableCalculationResult.selected_timetable.slots['1-1'].code = 'M1'

        expect(context.timetableV2Step).toBe('timetable-adoption')
        expect(context.adoptedTimetableCalculationResult.selected_timetable.slots['1-1'].code).toBe('D1')
        expect(context.adoptedTimetableSelectedNumber).toBe(3)
        expect(context.adoptedTimetableSelectionSnapshot).toEqual({
            language: 'E',
            semester: 1,
        })
        expect(context.adoptedTimetableStudentContextSnapshot.student.label).toBe('1C · PABINGER Elena · Semester 1')
        expect(context.$router.push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                mode: 'student',
                step: 'timetable-adoption',
                tt: '3',
            },
        })
    })

    it('shows the adopted timetable result from the frozen snapshot', () => {
        const context = timetableV2Context({
            adoptedTimetableCalculationResult: {
                full_green_timetable_count: 1,
                green_timetable_count: 0,
                selected_timetable: {
                    number: 2,
                    slots: {
                        '1-1': {
                            code: 'D1',
                            courseGroup: {
                                class_name: 'D1-A',
                            },
                            hour: 1,
                            weekday: 1,
                        },
                    },
                    type: 'full_green',
                },
            },
            adoptedTimetableSelectedNumber: 2,
            timetableCalculationResult: {
                selected_timetable: {
                    number: 1,
                    slots: {
                        '1-1': {
                            code: 'M1',
                            hour: 1,
                            weekday: 1,
                        },
                    },
                    type: 'full_green',
                },
            },
            timetableV2Step: 'timetable-adoption',
        })

        expect(context.selectedTimetableV2Result.slots['1-1'].code).toBe('D1')
        expect(context.timetableCalculationCardTitleLabel).toBe('Finaler Stundenplan Nr. 2')
        expect(context.selectedTimetableV2TitleLabel).toBe('')

        TimetableV2.methods.backToTimetableCalculation.call(context)

        expect(context.timetableV2Step).toBe('timetable-calculation')
    })

    it('builds the adopted timetable PDF payload from the selected V2 timetable', () => {
        const context = timetableV2Context({
            timetableV2Step: 'timetable-adoption',
            selectedSchoolyear: {
                name: 'Schuljahr 2025/26',
            },
            schoolHours: [
                {
                    hour: 10,
                    from: '17:05:00',
                    until: '18:35:00',
                },
            ],
            adoptedTimetableSelectedNumber: 2,
            adoptedTimetableSelectionSnapshot: {
                semester: 5,
            },
            adoptedTimetableStudentContextSnapshot: {
                student: {
                    label: '5K · SOLLEDER Luis · Semester 5',
                    semesterLabel: 'Semester 5',
                },
            },
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    selected_number: 2,
                    valid_count: 1,
                    type: 'valid',
                    slots: {
                        '1-10': {
                            code: 'INF2',
                            sourceLabel: 'INF2 - 1 - 5K - WE',
                            dateRangeLabel: '20.02. - 24.04.',
                            courseGroup: {
                                weekday: 1,
                                hour: 10,
                                dates: ['2026-02-20'],
                                recurrence_interval: 2,
                            },
                            sameSlotEntries: [
                                {
                                    code: 'INF3',
                                    sourceLabel: 'INF3 - 1 - 5K - WE',
                                    dateRangeLabel: '20.02. - 24.04.',
                                    courseGroup: {
                                        weekday: 1,
                                        hour: 10,
                                        dates: ['2026-03-06'],
                                    },
                                },
                            ],
                        },
                    },
                },
            },
        })

        const payload = context.adoptedTimetablePdfPayload()

        expect(payload.title).toBe('Stundenplan')
        expect(payload.subtitle).toBe('')
        expect(payload.schoolyear).toBe('Schuljahr 2025/26')
        expect(payload.student).toBe('5K · SOLLEDER Luis · Semester 5')
        expect(payload.weekdays).toEqual([
            { label: 'Mo' },
            { label: 'Di' },
            { label: 'Mi' },
            { label: 'Do' },
            { label: 'Fr' },
        ])
        expect(payload.print_options).toEqual({
            single_weeks: false,
            course_list: true,
            course_overview: true,
        })
        expect(payload.semesters[0].label).toBe('Semester 5')
        expect(payload.semesters[0].weeks[0].hours[0]).toMatchObject({
            hour: 10,
            from: '17:05',
            until: '18:35',
        })
        expect(payload.semesters[0].weeks[0].hours[0].cells[0]).toMatchObject({
            status: 'warning',
            courses: [
                {
                    label: 'INF2',
                    details: 'INF2 - 1 - 5K - WE\n2-wöchig: 20.02.',
                    dates: ['20.02.'],
                },
                {
                    label: 'INF3',
                    details: 'INF3 - 1 - 5K - WE\n06.03.',
                    dates: ['06.03.'],
                },
            ],
        })
    })

    it('opens a print dialog and serializes adopted timetable print options for the PDF payload', () => {
        const context = timetableV2Context({
            timetableV2Step: 'timetable-adoption',
            printOptions: {
                singleWeeks: true,
                courseList: false,
                courseOverview: true,
            },
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    type: 'valid',
                    slots: {},
                },
            },
        })

        context.openAdoptedTimetablePrintDialog()

        expect(context.printDialogVisible).toBe(true)
        expect(context.adoptedTimetablePdfPrintOptionsPayload()).toEqual({
            single_weeks: true,
            course_list: false,
            course_overview: true,
        })
        expect(context.adoptedTimetablePdfPayload(false).print_options).toBeUndefined()

        context.closeAdoptedTimetablePrintDialog()

        expect(context.printDialogVisible).toBe(false)
    })

    it('includes full compact same-slot course details in the adopted timetable PDF payload', () => {
        const context = timetableV2Context({
            timetableV2Step: 'timetable-adoption',
            selectedSchoolyear: {
                from: '2025-09-01',
                sem_2_start: '2026-02-16',
                until: '2026-07-11',
            },
            schoolHours: [
                {
                    hour: 5,
                    from: '13:00:00',
                    until: '13:45:00',
                },
            ],
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    type: 'valid',
                    slots: {
                        '2-5': {
                            code: 'M4',
                            sourceLabel: 'M4-3U-ALT',
                            dateRangeLabel: '21.02.-25.4.',
                            isKompaktunterrichtCourse: true,
                            courseGroup: {
                                hour: 5,
                                is_kompaktunterricht: true,
                                weekday: 2,
                            },
                            sameSlotEntries: [
                                {
                                    code: 'M5',
                                    sourceLabel: 'M5-3U-ALT',
                                    dateRangeLabel: '9.5.-11.7.',
                                    isAdditionalCourse: true,
                                    isKompaktunterrichtCourse: true,
                                    courseGroup: {
                                        hour: 5,
                                        is_kompaktunterricht: true,
                                        weekday: 2,
                                    },
                                },
                            ],
                        },
                    },
                },
            },
        })

        const payload = context.adoptedTimetablePdfPayload()
        const tuesdayCell = payload.semesters[0].weeks[0].hours[0].cells[1]

        expect(tuesdayCell.courses).toEqual([
            {
                dates: [],
                details: 'M4-3U-ALT\n21.02.-25.4. (Kompakt)',
                is_fu: false,
                label: 'M4',
                student_course_badge: '',
                student_course_type: '',
            },
            {
                dates: [],
                details: 'M5-3U-ALT\n9.5.-11.7. (Kompakt)',
                is_fu: false,
                label: 'M5',
                student_course_badge: 'Zusatz',
                student_course_type: 'additional',
            },
        ])
    })

    it('omits whole-semester date ranges from the adopted timetable PDF payload', () => {
        const context = timetableV2Context({
            timetableV2Step: 'timetable-adoption',
            selectedSchoolyear: {
                from: '2025-09-01',
                sem_2_start: '2026-02-16',
                until: '2026-07-11',
            },
            schoolHours: [
                {
                    hour: 5,
                    from: '13:00:00',
                    until: '13:45:00',
                },
            ],
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    type: 'valid',
                    slots: {
                        '2-5': {
                            code: 'M4',
                            sourceLabel: 'M4-3U-ALT',
                            dateRangeLabel: '16.02.-11.07.',
                            isKompaktunterrichtCourse: true,
                            courseGroup: {
                                hour: 5,
                                is_kompaktunterricht: true,
                                weekday: 2,
                            },
                        },
                    },
                },
            },
        })

        const payload = context.adoptedTimetablePdfPayload()
        const tuesdayCell = payload.semesters[0].weeks[0].hours[0].cells[1]

        expect(tuesdayCell.courses[0].details).toBe('M4-3U-ALT\nKompakt')
    })

    it('omits regular date ranges from non-compact courses in the adopted timetable PDF payload', () => {
        const context = timetableV2Context({
            timetableV2Step: 'timetable-adoption',
            schoolHours: [
                {
                    hour: 2,
                    from: '08:50:00',
                    until: '09:35:00',
                },
            ],
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    type: 'valid',
                    slots: {
                        '3-2': {
                            code: 'F2',
                            sourceLabel: 'F2-3C-SCHO',
                            dateRangeLabel: '16.02.-6.7.',
                            courseGroup: {
                                hour: 2,
                                weekday: 3,
                            },
                        },
                    },
                },
            },
        })

        const payload = context.adoptedTimetablePdfPayload()
        const wednesdayCell = payload.semesters[0].weeks[0].hours[0].cells[2]

        expect(wednesdayCell.courses[0].details).toBe('F2-3C-SCHO')
    })

    it('exports one-day overlaps as PDF marker chips instead of normal courses', () => {
        const context = timetableV2Context({
            timetableV2Step: 'timetable-adoption',
            schoolHours: [
                {
                    hour: 7,
                    from: '14:45:00',
                    until: '15:30:00',
                },
            ],
            adoptedTimetableSelectionSnapshot: {
                semester: 5,
            },
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    type: 'valid',
                    slots: {
                        '5-7': {
                            code: 'D1',
                            sourceLabel: 'D1 - 1 - 5K - UNT',
                            dateRangeLabel: '20.02. - 24.04.',
                            courseGroup: {
                                weekday: 5,
                                hour: 7,
                            },
                            conflicts: [
                                {
                                    code: 'E7',
                                    sourceLabel: 'E7 - 1 - 5K - ONE',
                                    courseGroup: {
                                        weekday: 5,
                                        hour: 7,
                                        dates: ['2026-03-06'],
                                    },
                                    isOccasional: true,
                                },
                            ],
                        },
                    },
                },
            },
        })

        const payload = context.adoptedTimetablePdfPayload()
        const fridayCell = payload.semesters[0].weeks[0].hours[0].cells[4]

        expect(fridayCell).toMatchObject({
            status: 'filled',
            courses: [
                {
                    label: 'D1',
                    details: 'D1 - 1 - 5K - UNT',
                },
            ],
            markers: [
                {
                    label: 'E7',
                    title: 'E7 - 1 - 5K - ONE 06.03.',
                },
            ],
        })
    })

    it('saves the adopted V2 timetable as a published student timetable', async () => {
        const previousAxios = globalThis.axios
        const axiosMock = {
            post: vi.fn(() => Promise.resolve({
                data: {
                    message: 'Stundenplan für GEHMACHER Ella wurde gespeichert.',
                    data: {
                        id: 42,
                        student_code: '1001',
                        published_at: '2026-06-23T12:00:00+00:00',
                    },
                },
            })),
        }
        globalThis.axios = axiosMock

        const context = timetableV2Context({
            timetableV2Step: 'timetable-adoption',
            selectedSchoolyear: {
                name: 'Schuljahr 2025/26',
            },
            robotStudents: [
                {
                    student_code: '1001',
                    first_name: 'Ella',
                    last_name: 'GEHMACHER',
                },
            ],
            schoolHours: [
                {
                    hour: 1,
                    from: '08:00:00',
                    until: '08:50:00',
                },
            ],
            adoptedTimetableSelectedNumber: 4,
            adoptedTimetableSelectionSnapshot: {
                language: 'L',
                semester: 5,
            },
            adoptedTimetableCalculationOptionsSnapshot: {
                maxFreeDays: true,
                noDistanceLearning: false,
                noSaturday: true,
                startsFromPeriod10: false,
            },
            adoptedTimetableStudentContextSnapshot: {
                student: {
                    label: '5K · GEHMACHER Ella · Semester 5',
                    semesterLabel: 'Semester 5',
                    studentCode: '1001',
                },
            },
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    selected_number: 4,
                    type: 'valid',
                    slots: {
                        '1-1': {
                            code: 'D1',
                            sourceLabel: 'D1 - 1 - 5K - UNT',
                            courseGroup: {
                                weekday: 1,
                                hour: 1,
                            },
                        },
                    },
                },
            },
        })

        try {
            expect(context.adoptedTimetableSaveVisible).toBe(true)

            await context.saveAdoptedPublishedStudentTimetable()

            expect(axiosMock.post).toHaveBeenCalledWith(
                '/api/admin/students-timetables/overview/student-timetable',
                expect.objectContaining({
                    student_code: '1001',
                    student_label: 'GEHMACHER Ella',
                    timetable: expect.objectContaining({
                        title: 'Stundenplan',
                        subtitle: '',
                    }),
                    state: expect.objectContaining({
                        source: 'timetable-v2',
                        timetableV2Step: 'timetable-adoption',
                        timetableV2SelectedNumber: 4,
                        timetableV2Selection: {
                            language: 'L',
                            semester: 5,
                        },
                    }),
                }),
            )
            expect(context.adoptedPublishedTimetableReport).toEqual({
                type: 'success',
                message: 'Stundenplan für GEHMACHER Ella wurde gespeichert.',
            })
            expect(context.robotStudents[0]).toMatchObject({
                has_published_timetable: true,
                published_timetable_id: 42,
                published_timetable_at: '2026-06-23T12:00:00+00:00',
            })
        } finally {
            globalThis.axios = previousAxios
        }
    })

    it('clears the adopted timetable save report on restart', () => {
        const context = timetableV2Context({
            adoptedPublishedTimetableReport: {
                type: 'success',
                message: 'Stundenplan für SOLLEDER Luis wurde gespeichert.',
            },
        })

        TimetableV2.methods.restartTimetableV2.call(context)

        expect(context.adoptedPublishedTimetableReport).toEqual({
            type: 'success',
            message: '',
        })
    })

    it('clears the adopted timetable save report when another student is selected', () => {
        const context = timetableV2Context({
            adoptedPublishedTimetableReport: {
                type: 'success',
                message: 'Stundenplan für SOLLEDER Luis wurde gespeichert.',
            },
            robotStudents: [
                {
                    student_code: '1001',
                    first_name: 'Ella',
                    last_name: 'GEHMACHER',
                    class: '1C',
                    school_level: 1,
                },
            ],
            studentSelectionDraft: {
                studentCode: '1001',
            },
        })

        TimetableV2.methods.updateStudentSelection.call(context)

        expect(context.adoptedPublishedTimetableReport).toEqual({
            type: 'success',
            message: '',
        })
    })

    it('keeps the student dialog open when updating without a selected student', () => {
        const context = timetableV2Context({
            robotStudents: [],
            studentDialogOpen: true,
            studentSearch: 'sol',
            studentSelectionDraft: {
                studentCode: null,
            },
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: {
                    student: {
                        studentCode: '1001',
                    },
                },
            },
        })

        const result = TimetableV2.methods.updateStudentSelection.call(context)

        expect(result).toBeNull()
        expect(context.studentDialogOpen).toBe(true)
        expect(context.studentSearch).toBe('sol')
        expect(context.saveStoredTimetableState).not.toHaveBeenCalled()
        expect(context.loadStoredStudentOverview).not.toHaveBeenCalled()
    })

    it('restores the adopted timetable result from stored state after refresh', async () => {
        const calculateTimetables = vi.fn()
        const context = timetableV2Context({
            $route: {
                path: '/admin/students-timetables/timetable-v2/overview',
                query: {
                    mode: 'student',
                    step: 'timetable-adoption',
                    tt: '2',
                },
            },
            calculateTimetables,
            adoptedTimetableCalculationResult: null,
            storedTimetableState: {
                timetableV2Adoption: {
                    adoptedCalculationResult: {
                        selected_timetable: {
                            number: 2,
                            slots: {
                                '1-1': {
                                    code: 'BU2',
                                    sourceLabel: 'BU2-A',
                                },
                            },
                            type: 'full_green',
                        },
                    },
                    calculationOptions: {
                        maxFreeDays: true,
                        noDistanceLearning: false,
                        noSaturday: true,
                        startsFromPeriod10: false,
                    },
                    calculationResult: {
                        selected_timetable: {
                            number: 2,
                            slots: {
                                '1-1': {
                                    code: 'BU2',
                                    sourceLabel: 'BU2-A',
                                },
                            },
                            type: 'full_green',
                        },
                    },
                    calculationSelection: {
                        semester: 5,
                    },
                    selectedNumber: 2,
                    selection: {
                        courseSelections: {
                            'planned:D1': false,
                        },
                        semester: 5,
                    },
                    studentContext: {
                        student: {
                            label: '5K · SOLLEDER Luis · Semester 5',
                        },
                    },
                },
                timetableV2Selection: {
                    semester: 5,
                },
            },
            timetableV2Step: 'timetable-adoption',
        })

        await TimetableV2.methods.restoreTimetableV2RouteStepEffects.call(context, {
            courseGroupsPromise: Promise.resolve(),
            schoolHoursPromise: Promise.resolve(),
            subjectRowsPromise: Promise.resolve(),
        })

        expect(calculateTimetables).not.toHaveBeenCalled()
        expect(context.adoptedTimetableCalculationResult.selected_timetable.slots['1-1'].code).toBe('BU2')
        expect(context.adoptedTimetableSelectedNumber).toBe(2)
        expect(context.adoptedTimetableSelectionSnapshot.courseSelections).toEqual({
            'planned:D1': false,
        })
        expect(context.adoptedTimetableCalculationSelectionSnapshot).toEqual({
            semester: 5,
        })
        expect(context.adoptedTimetableStudentContextSnapshot.student.label).toBe('5K · SOLLEDER Luis · Semester 5')
        expect(context.timetableNoSaturdaySelected).toBe(true)
        expect(context.timetableMaxFreeDaysSelected).toBe(true)
    })

    it('recalculates the adopted timetable result from the route after refresh when no snapshot exists', async () => {
        let context: ReturnType<typeof timetableV2Context>
        const calculateTimetables = vi.fn(() => {
            context.timetableCalculationResult = {
                full_green_timetable_count: 1,
                green_timetable_count: 0,
                selected_timetable: {
                    number: 1,
                    slots: {
                        '1-1': {
                            code: 'BU2',
                            courseGroup: {
                                class_name: 'BU2-A',
                            },
                            hour: 1,
                            weekday: 1,
                        },
                    },
                    type: 'full_green',
                },
            }

            return Promise.resolve(context.timetableCalculationResult)
        })

        context = timetableV2Context({
            $route: {
                path: '/admin/students-timetables/timetable-v2/overview',
                query: {
                    mode: 'student',
                    step: 'timetable-adoption',
                    tt: '1',
                },
            },
            calculateTimetables,
            adoptedTimetableCalculationResult: null,
            storedTimetableState: {
                timetableV2Selection: {
                    semester: 5,
                },
                transferredStudentContext: {
                    student: {
                        label: '5K · SOLLEDER Luis · Semester 5',
                    },
                },
            },
            storedTimetableStudentContext: {
                student: {
                    label: '5K · SOLLEDER Luis · Semester 5',
                },
            },
            timetableV2Step: 'timetable-adoption',
        })

        await TimetableV2.methods.restoreTimetableV2RouteStepEffects.call(context, {
            courseGroupsPromise: Promise.resolve(),
            schoolHoursPromise: Promise.resolve(),
            subjectRowsPromise: Promise.resolve(),
        })

        expect(calculateTimetables).toHaveBeenCalledOnce()
        expect(context.adoptedTimetableCalculationResult.selected_timetable.slots['1-1'].code).toBe('BU2')
        expect(context.adoptedTimetableSelectedNumber).toBe(1)
        expect(context.adoptedTimetableSelectionSnapshot).toEqual({
            semester: 5,
        })
        expect(context.adoptedTimetableStudentContextSnapshot.student.label).toBe('5K · SOLLEDER Luis · Semester 5')
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

    it('opens course review after keeping selected course cards and clearing offered selections', () => {
        const storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                courseSelections: {
                    'planned:D1': true,
                    'planned:GW1': true,
                },
                moreOfferedCourseSelections: {
                    'additional:INF1::INF1-A': true,
                },
                offeredCourseSelections: {
                    'planned:D1::D1-A': false,
                },
            },
            transferredStudentContext: null,
        }
        let context
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })
        context = timetableV2Context({
            saveStoredTimetableState,
            storedTimetableState,
            storedTimetableStateForSaving: () => storedTimetableState,
            selectedCourseItems: [
                { label: 'D1', selectionKey: 'planned:D1' },
                { label: 'GW1', selectionKey: 'planned:GW1' },
            ],
        })

        TimetableV2.methods.openCourseReview.call(context)

        expect(context.timetableV2Step).toBe('course-review')
        expect(saveStoredTimetableState).toHaveBeenCalledOnce()
        expect(saveStoredTimetableState).toHaveBeenCalledWith({
            selection: {},
            timetableV2Selection: {
                courseSelections: {
                    'planned:D1': true,
                    'planned:GW1': true,
                },
            },
            transferredStudentContext: null,
        })
        expect(context.storedTimetableState.timetableV2Selection).toEqual({
            courseSelections: {
                'planned:D1': true,
                'planned:GW1': true,
            },
        })
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

    it('clears selected course details when restoring the calculation step', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { label: 'D1', selectionKey: 'planned:D1' },
                { label: 'GW1', selectionKey: 'planned:GW1' },
            ],
            selectedReviewCourseKey: 'planned:GW1',
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        context.loadCourseGroups.mockClear()
        context.loadSchoolHours.mockClear()

        TimetableV2.methods.restoreTimetableV2RouteStepEffects.call(context)

        expect(context.selectedReviewCourseKey).toBe('')
        expect(context.selectedReviewCourseItem).toBeNull()
        expect(context.selectedCourseOfferCardVisible).toBe(false)
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

    it('closes the offered courses card when the active review course is clicked again', () => {
        const context = timetableV2Context({
            courseReviewVisible: true,
            selectedCourseItems: [
                { label: 'D1', selectionKey: 'planned:D1' },
                { label: 'INF1', selectionKey: 'planned:INF1' },
            ],
        })

        TimetableV2.methods.selectReviewCourse.call(context, context.selectedCourseItems[1])

        expect(context.selectedReviewCourseKey).toBe('planned:INF1')
        expect(context.selectedReviewCourseItem).toEqual(context.selectedCourseItems[1])

        TimetableV2.methods.selectReviewCourse.call(context, context.selectedCourseItems[1])

        expect(context.selectedReviewCourseKey).toBe('')
        expect(context.selectedReviewCourseItem).toBeNull()
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

    it('maps route steps to the four-step progress indicator', () => {
        const selectionContext = timetableV2Context({
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: {
                    student: { label: '4S · Test Student' },
                    courses: {},
                },
            },
            timetableStartMode: 'student',
            timetableV2Step: 'selection',
        })
        const calculationContext = {
            ...selectionContext,
            timetableV2Step: 'timetable-calculation',
            timetableV2StepNumber: 3,
        }
        const adoptionContext = {
            ...selectionContext,
            timetableV2Step: 'timetable-adoption',
            timetableV2StepNumber: 4,
        }

        expect(TimetableV2.computed.timetableV2StepperVisible.call(selectionContext)).toBe(true)
        expect(TimetableV2.computed.timetableV2StepNumber.call(selectionContext)).toBe(1)
        expect(TimetableV2.computed.timetableV2StepNumber.call(calculationContext)).toBe(3)
        expect(TimetableV2.computed.timetableV2StepNumber.call(adoptionContext)).toBe(4)
        expect(TimetableV2.computed.timetableV2StepperItems.call(calculationContext).map((item) => ({
            complete: item.complete,
            title: item.title,
            value: item.value,
        }))).toEqual([
            { complete: true, title: 'Auswahl', value: 1 },
            { complete: true, title: 'Module', value: 2 },
            { complete: false, title: 'Stundenplan', value: 3 },
            { complete: false, title: 'Übernahme', value: 4 },
        ])
    })

    it('closes stale more courses card state when restoring a calculation route without more courses', () => {
        const context = timetableV2Context({
            $route: {
                path: '/admin/students-timetables/timetable-v2/overview',
                query: {
                    mode: 'student',
                    step: 'timetable-calculation',
                    tt: '1',
                },
            },
            moreCoursesVisible: false,
            moreCoursesCardVisible: true,
            selectedMoreCourseKey: 'planned:M4',
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.applyTimetableV2RouteFromRoute.call(context, {
            restoreEffects: false,
        })

        expect(context.timetableV2Step).toBe('timetable-calculation')
        expect(context.moreCoursesVisible).toBe(false)
        expect(context.moreCoursesCardVisible).toBe(false)
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.$router.replace).not.toHaveBeenCalled()
    })

    it('does not preload review-only data while mounted on the selection step', async () => {
        const context = timetableV2Context({
            initialRouteLoading: true,
            loadTimetableV2SelectionBootstrap: vi.fn(() => Promise.resolve()),
            loadSubjectRows: vi.fn(() => Promise.resolve()),
        })

        await TimetableV2.mounted.call(context)

        expect(context.initialRouteLoading).toBe(false)
        expect(context.loadTimetableV2SelectionBootstrap).toHaveBeenCalledWith('', {
            applyStoredState: true,
            persistStudentOverviewState: false,
        })
        expect(context.loadSubjectRows).not.toHaveBeenCalled()
        expect(context.loadStoredStudentOverview).not.toHaveBeenCalled()
        expect(context.loadCourseGroups).not.toHaveBeenCalled()
        expect(context.loadSchoolHours).not.toHaveBeenCalled()
    })

    it('loads robot students on mount when a restored selected student is missing email data', async () => {
        const context = timetableV2Context({
            loadRobotStudents: vi.fn(() => Promise.resolve()),
            loadTimetableV2SelectionBootstrap: vi.fn(() => Promise.resolve()),
            storedTimetableState: {
                transferredStudentContext: {
                student: {
                    studentCode: '200',
                    label: '5C · ZADRA Isabella · Semester 5',
                },
            },
            },
            storedTimetableStudentContext: {
                student: {
                    studentCode: '200',
                    label: '5C Â· ZADRA Isabella Â· Semester 5',
                },
            },
        })

        await TimetableV2.mounted.call(context)

        expect(context.loadRobotStudents).toHaveBeenCalledOnce()
        expect(context.loadTimetableV2SelectionBootstrap).toHaveBeenCalledWith('', {
            applyStoredState: true,
            persistStudentOverviewState: false,
        })
        expect(context.loadStoredStudentOverview).not.toHaveBeenCalled()
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

    it('shows an initial loader while the timetable v2 route is hydrating', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const context = timetableV2Context({
            initialRouteLoading: true,
        })

        expect(TimetableV2.computed.timetableV2PageLoading.call(context)).toBe(true)
        expect(source).toContain('v-if="initialRouteLoading"')
        expect(source).toContain('class="students-timetable-v2-initial-loader"')
        expect(source).toContain('Stundenplan wird geladen')
    })

    it('disables timetable v2 page actions until the page is fully loaded', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const context = timetableV2Context({
            courseGroupsLoading: true,
            schoolHoursLoading: true,
            timetableV2Step: 'timetable-adoption',
        })

        expect(TimetableV2.computed.timetableV2PageLoading.call(context)).toBe(true)
        expect(TimetableV2.computed.timetableV2PageActionsDisabled.call(context)).toBe(true)

        context.courseGroupsLoading = false
        context.schoolHoursLoading = false
        context.storedTimetableStateLoading = true

        expect(TimetableV2.computed.timetableV2PageLoading.call(context)).toBe(true)
        expect(TimetableV2.computed.timetableV2PageActionsDisabled.call(context)).toBe(true)

        context.storedTimetableStateLoading = false

        expect(TimetableV2.computed.timetableV2PageActionsDisabled.call(context)).toBe(false)
        expect(source).toContain("'students-timetable-v2-page--actions-disabled': timetableV2PageActionsDisabled")
        expect(source).toContain("'students-timetable-v2-page--draft-pending': courseSelectionDraftPending")
        expect(source).toContain('@draft-change="courseSelectionDraftPending = $event"')
        expect(source).toContain('@draft-selections-change="setDraftCourseSelections"')
        expect(source).toContain(':inert="timetableV2PageActionsDisabled ? \'\' : null"')
        expect(source).toContain('v-if="timetableV2PageLoading"')
        expect(source).toContain('students-timetable-v2-loading-dots')
        expect(source).toContain('.students-timetable-v2-page--actions-disabled :deep(button)')
        expect(source).toContain('.students-timetable-v2-page--actions-disabled :deep([role="button"])')
        expect(source).toContain('.students-timetable-v2-page--draft-pending :deep(button)')
        expect(source).toContain('.students-timetable-v2-page--draft-pending :deep([role="button"])')
        expect(source).toContain('.students-timetable-v2-page--draft-pending :deep(.students-timetable-v2-course-selection-draft-action)')
        expect(source).toContain('.students-timetable-v2-page--draft-pending :deep(.students-timetable-v2-course-selection-interactive)')
        expect(source).toContain('@keyframes students-timetable-v2-loading-dots')
    })

    it('shows the course review button card while review data is loading', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const context = timetableV2Context({
            courseReviewVisible: true,
            courseGroupsLoading: true,
            schoolHoursLoading: true,
            subjectRowsLoading: true,
        })

        expect(TimetableV2.computed.timetableV2PageLoading.call(context)).toBe(true)
        expect(TimetableV2.computed.reviewButtonCardVisible.call(context)).toBe(true)
        expect(source).toContain(':disabled="timetableV2PageLoading"')
        expect(source).toContain(':loading="timetableV2PageLoading"')
        expect(source).toContain('class="students-timetable-v2-restart-card__loading"')
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

    it('shows filtered calculation counts when max free days is selected', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                timetable_variation_count: 440,
                full_green_timetable_count: 340,
                green_timetable_count: 0,
                conflict_timetable_count: 100,
                selected_quality_criteria_count: 7,
                selected_timetable: {
                    number: 1,
                    type: 'full_green',
                    slots: {},
                },
            },
            timetableMaxFreeDaysSelected: true,
        })

        expect(context.timetableQualityCriteriaRequired).toBe(true)
        expect(context.timetableCalculationDisplayTotalCount).toBe(7)
        expect(TimetableV2.computed.timetableCalculationCountLabel.call(context)).toBe('7 Stundenpläne gesamt')
        expect(context.timetableCalculationResultCountItems.map((item) => item.label)).toEqual([
            '7 gültig',
            '0 Konflikte',
        ])
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
        const moreCourseAvailabilityPayload = TimetableV2.methods.timetableV2CalculationPayloadForMoreCourseAvailability.call(context, {
            code: 'E2',
            courseGroup: 'missing',
            key: 'E2',
            label: 'E2',
            selectionKey: 'missing:E2',
        })

        expect(payload.selected_timetable_type).toBe('conflict')
        expect(payload.selected_timetable_number).toBe(1)
        expect(moreCourseAvailabilityPayload.selected_timetable_type).toBe('conflict')
        expect(moreCourseAvailabilityPayload.selected_timetable_number).toBe(1)
    })

    it('loads a conflict timetable when the first calculation finds no valid result', async () => {
        let context: ReturnType<typeof timetableV2Context>
        const requestPayloads: any[] = []
        const requestTimetableV2Calculation = vi.fn((options = {}) => {
            requestPayloads.push(TimetableV2.methods.timetableV2CalculationPayload.call(context, options))

            return Promise.resolve({
                data: {
                    data: requestPayloads.length === 1
                        ? {
                            timetable_variation_count: 16,
                            full_green_timetable_count: 0,
                            green_timetable_count: 0,
                            conflict_timetable_count: 16,
                            selected_timetable: null,
                        }
                        : {
                            timetable_variation_count: 16,
                            full_green_timetable_count: 0,
                            green_timetable_count: 0,
                            conflict_timetable_count: 16,
                            selected_timetable: {
                                number: 1,
                                slots: {},
                                type: 'conflict',
                            },
                        },
                },
            })
        })

        context = timetableV2Context({
            calculateTimetables: TimetableV2.methods.calculateTimetables,
            requestTimetableV2Calculation,
            timetableCalculationSelectedNumber: 1,
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        await TimetableV2.methods.calculateTimetables.call(context)

        expect(requestTimetableV2Calculation).toHaveBeenCalledTimes(2)
        expect(requestPayloads.map((payload) => payload.selected_timetable_type)).toEqual([
            'full_green',
            'conflict',
        ])
        expect(requestPayloads.map((payload) => payload.selected_timetable_number)).toEqual([1, 1])
        expect(context.timetableCalculationResult.selected_timetable.type).toBe('conflict')
        expect(context.selectedTimetableV2ResultType).toBe('conflict')
        expect(context.selectedTimetableV2TitleLabel).toBe('Stundenplan mit Überschneidung')
        expect(context.timetableCalculationResultStatusLabel).toBe('Die Stundenpläne wurden erfolgreich erstellt.')
    })

    it('loads conflict timetables without selected quality filters when no valid result exists', async () => {
        let context: ReturnType<typeof timetableV2Context>
        const requestPayloads: any[] = []
        const requestTimetableV2Calculation = vi.fn((options = {}) => {
            requestPayloads.push(TimetableV2.methods.timetableV2CalculationPayload.call(context, options))

            return Promise.resolve({
                data: {
                    data: requestPayloads.length === 1
                        ? {
                            timetable_variation_count: 3,
                            full_green_timetable_count: 0,
                            green_timetable_count: 0,
                            conflict_timetable_count: 3,
                            selected_quality_criteria_count: 0,
                            selected_timetable: null,
                        }
                        : {
                            timetable_variation_count: 3,
                            full_green_timetable_count: 0,
                            green_timetable_count: 0,
                            conflict_timetable_count: 3,
                            selected_quality_criteria_count: 0,
                            selected_timetable: {
                                number: 1,
                                slots: {},
                                type: 'conflict',
                            },
                        },
                },
            })
        })

        context = timetableV2Context({
            calculateTimetables: TimetableV2.methods.calculateTimetables,
            requestTimetableV2Calculation,
            timetableCalculationSelectedNumber: 1,
            timetableCalculationVisible: true,
            timetableMaxFreeDaysSelected: true,
            timetableV2Step: 'timetable-calculation',
        })

        await TimetableV2.methods.calculateTimetables.call(context)

        expect(requestPayloads.map((payload) => payload.selected_timetable_type)).toEqual([
            'full_green',
            'conflict',
        ])
        expect(requestPayloads[0].selected_quality_criteria_required).toBe(true)
        expect(requestPayloads[0].selected_quality_criterion_keys).toEqual(['free_days'])
        expect(requestPayloads[1].selected_quality_criteria_required).toBe(false)
        expect(requestPayloads[1].selected_quality_criterion_keys).toEqual([])
        expect(context.selectedTimetableV2ResultType).toBe('conflict')
        expect(context.selectedTimetableV2CounterLabel).toBe('1 / 3 Konflikte')
        expect(context.timetableCalculationResultCountItems.map((item) => item.label)).toEqual([
            '0 gültig',
            '3 Konflikte',
        ])

        const directConflictPayload = TimetableV2.methods.timetableV2CalculationPayload.call(context, {
            selectedTimetableNumber: 2,
            selectedTimetableType: 'conflict',
        })

        expect(directConflictPayload.selected_quality_criteria_required).toBe(false)
        expect(directConflictPayload.selected_quality_criterion_keys).toEqual([])
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
        expect(calculateTimetables).toHaveBeenCalledWith({ refreshAuxiliary: false })
    })

    it('shows a concrete timetable number after entering it directly', () => {
        const calculateTimetables = vi.fn()
        const context = timetableV2Context({
            calculateTimetables,
            timetableCalculationNumberDraft: '1',
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

        TimetableV2.methods.updateTimetableV2NumberDraft.call(context, '3')
        TimetableV2.methods.commitSelectedTimetableV2Number.call(context)

        expect(context.timetableCalculationSelectedNumber).toBe(3)
        expect(context.timetableCalculationNumberDraft).toBe('3')
        expect(context.$router.push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v2/overview',
            query: {
                step: 'timetable-calculation',
                tt: '3',
            },
        })
        expect(calculateTimetables).toHaveBeenCalledWith({ refreshAuxiliary: false })
    })

    it('clamps a directly entered timetable number to the available range', () => {
        const calculateTimetables = vi.fn()
        const context = timetableV2Context({
            calculateTimetables,
            timetableCalculationNumberDraft: '99',
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

        TimetableV2.methods.commitSelectedTimetableV2Number.call(context)

        expect(context.timetableCalculationSelectedNumber).toBe(4)
        expect(context.timetableCalculationNumberDraft).toBe('4')
        expect(calculateTimetables).toHaveBeenCalledWith({ refreshAuxiliary: false })
    })

    it('does not refresh auxiliary data when only the timetable route number changes', () => {
        const calculateTimetables = vi.fn()
        const context = timetableV2Context({
            $route: {
                path: '/admin/students-timetables/timetable-v2/overview',
                query: {
                    step: 'timetable-calculation',
                    tt: '3',
                },
            },
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
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.applyTimetableV2RouteFromRoute.call(context, { syncRoute: false })

        expect(context.timetableCalculationSelectedNumber).toBe(3)
        expect(calculateTimetables).toHaveBeenCalledWith({ refreshAuxiliary: false })
    })

    it('keeps offered courses closed from the calculation summary selected courses', () => {
        const context = timetableV2Context({
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
            selectedCourseItems: [
                { label: 'E2', selectionKey: 'missing:E2' },
                { label: 'D1', selectionKey: 'planned:D1' },
            ],
        })

        TimetableV2.methods.selectReviewCourse.call(context, context.selectedCourseItems[0])

        expect(context.selectedCourseItemsClickable).toBe(false)
        expect(context.selectedReviewCourseKey).toBe('')
        expect(context.selectedReviewCourseItem).toBeNull()
        expect(context.selectedCourseOfferCardVisible).toBe(false)
        expect(context.selectedCourseOfferItemsSelectable).toBe(false)
    })

    it('keeps calculation summary offered courses hidden and read-only', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const saveStoredTimetableState = vi.fn()
        const usedOfferedCourse = { key: 'E2-A', selectionKey: 'missing:E2::offer-a' }
        const unusedOfferedCourse = { key: 'E2-B', selectionKey: 'missing:E2::offer-b' }
        const context = timetableV2Context({
            offeredCourseItemsForSelectedCourse: () => [usedOfferedCourse, unusedOfferedCourse],
            saveStoredTimetableState,
            selectedCourseItems: [
                { label: 'E2', selectionKey: 'missing:E2' },
            ],
            selectedReviewCourseKey: 'missing:E2',
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    offeredCourseSelections: {
                        'missing:E2::offer-b': false,
                    },
                },
                transferredStudentContext: null,
            },
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.toggleSelectedReviewOfferedCourseItem.call(context, usedOfferedCourse)

        expect(source).toContain("'students-timetable-v2-offered-courses-card__item--selected': offeredCourseSelected(course)")
        expect(source).toContain("'students-timetable-v2-offered-courses-card__item--deselected': !offeredCourseSelected(course)")
        expect(TimetableV2.methods.offeredCourseSelected.call(context, usedOfferedCourse)).toBe(true)
        expect(TimetableV2.methods.offeredCourseSelected.call(context, unusedOfferedCourse)).toBe(false)
        expect(context.selectedCourseOfferCardVisible).toBe(false)
        expect(context.selectedCourseOfferItemsSelectable).toBe(false)
        expect(saveStoredTimetableState).not.toHaveBeenCalled()
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

    it('sends deselected offered course groups for a selected course calculation', () => {
        const context = timetableV2Context({
            courseGroups: [
                {
                    class_name: 'E2-1R-REIS',
                    course: 'E',
                    hour: 13,
                    module_code: 'E2',
                    semester: 2,
                    weekday: 1,
                },
                {
                    class_name: 'E2-2A-RAI',
                    course: 'E',
                    hour: 12,
                    module_code: 'E2',
                    recurrence_label: '2-wöchig',
                    semester: 2,
                    weekday: 2,
                },
            ],
            selectedCourseItems: [
                {
                    code: 'E2',
                    courseGroup: 'missing',
                    hours: 3,
                    key: 'E2-1',
                    label: 'E2',
                    selectionKey: 'missing:E2',
                },
            ],
            storedTimetableState: {
                timetableV2Selection: {
                    offeredCourseSelections: {
                        'missing:E2::2|E|E22ARAI': false,
                    },
                },
            },
        })
        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.selected_course_keys).toEqual(['E2-1'])
        expect(payload.deselected_course_group_keys).toEqual(['E2|E2-2A-RAI'])
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

    it('checks more course availability with candidate courses', () => {
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
        expect(payload.selected_additional_course_keys).toEqual([])
        expect(payload.selected_additional_courses_required).toBe(false)
        expect(payload.selected_timetable_type).toBe('full_green')
        expect(payload.selected_timetable_number).toBe(1)
        expect(payload.availability_only).toBe(true)
        expect(payload.candidate_courses).toEqual([
            {
                availability_key: moreCourse.selectionKey,
                course_group: 'additional',
                course_key: 'INF2',
            },
        ])
    })

    it('uses the normalized course code for synthetic missing course availability keys', () => {
        const context = timetableV2Context({
            storedMissingCourseCardItems: [
                { code: 'E2', hours: 3, key: 'E2-1', label: 'E2' },
            ],
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'E2-1')

        const payload = TimetableV2.methods.timetableV2CalculationPayloadForMoreCourseAvailability.call(context, moreCourse)

        expect(TimetableV2.methods.timetableV2CalculationCourseKey.call(context, moreCourse)).toBe('E2-1')
        expect(payload.candidate_courses).toEqual([
            {
                availability_key: moreCourse.selectionKey,
                course_group: 'missing',
                course_key: 'E2',
            },
        ])
    })

    it('checks individual more course offer availability for chip colors', () => {
        const context = timetableV2Context({
            courseGroups: [
                {
                    display_label: 'INF2 - A',
                    hour: 1,
                    key: 'inf2-a',
                    semester: 1,
                    teacher: 'A',
                    title: 'INF2',
                    weekday: 1,
                },
                {
                    display_label: 'INF2 - B',
                    hour: 2,
                    key: 'inf2-b',
                    semester: 1,
                    teacher: 'B',
                    title: 'INF2',
                    weekday: 2,
                },
            ],
            courseGroupsLoaded: true,
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'D1', selectionKey: 'planned:D1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'INF2')
        const offeredCourses = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
        const offerAvailabilityKeys = offeredCourses.map((offeredCourse) =>
            TimetableV2.methods.moreCourseOfferAvailabilityKey.call(context, moreCourse, offeredCourse))
        const offerSelectionKeys = offeredCourses.map((offeredCourse) => offeredCourse.selectionKey)

        const payload = TimetableV2.methods.timetableV2CalculationPayloadForMoreCourseAvailability.call(context, moreCourse)

        expect(offeredCourses).toHaveLength(2)
        expect(payload.candidate_courses).toEqual([
            {
                availability_key: moreCourse.selectionKey,
                course_group: 'additional',
                course_key: 'INF2',
            },
            {
                availability_key: offerAvailabilityKeys[0],
                course_group: 'additional',
                course_key: 'INF2',
                deselected_course_group_keys: [offeredCourses[1].backendSelectionKey],
            },
            {
                availability_key: offerAvailabilityKeys[1],
                course_group: 'additional',
                course_key: 'INF2',
                deselected_course_group_keys: [offeredCourses[0].backendSelectionKey],
            },
        ])

        context.storedTimetableState = {
            timetableV2Selection: {
                moreOfferedCourseSelections: Object.fromEntries(offerSelectionKeys.map((selectionKey) => [selectionKey, true])),
            },
        }
        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: true,
            [offerAvailabilityKeys[0]]: true,
            [offerAvailabilityKeys[1]]: true,
        }
        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('success')

        context.storedTimetableState = {
            timetableV2Selection: {
                moreOfferedCourseSelections: {
                    [offerSelectionKeys[1]]: true,
                },
            },
        }
        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: true,
        }
        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('warning')

        context.storedTimetableState = {
            timetableV2Selection: {
                moreOfferedCourseSelections: Object.fromEntries(offerSelectionKeys.map((selectionKey) => [selectionKey, true])),
            },
        }
        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: true,
            [offerAvailabilityKeys[0]]: true,
            [offerAvailabilityKeys[1]]: false,
        }
        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('warning')
        expect(TimetableV2.methods.moreCourseOfferUnavailable.call(context, offeredCourses[0], moreCourse)).toBe(false)
        expect(TimetableV2.methods.moreCourseOfferUnavailable.call(context, offeredCourses[1], moreCourse)).toBe(true)
        expect(TimetableV2.methods.moreCourseOfferDisabled.call(context, offeredCourses[1], moreCourse)).toBe(true)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, offeredCourses[1], moreCourse)).toBe(false)
        expect(TimetableV2.methods.moreOfferedCourseSelectionsForSingleCourse.call(context, moreCourse)).toEqual({
            [offerSelectionKeys[0]]: true,
        })

        const saveCallCount = context.saveStoredTimetableState.mock.calls.length
        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, offeredCourses[1], moreCourse)

        expect(context.saveStoredTimetableState).toHaveBeenCalledTimes(saveCallCount)

        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: false,
            [offerAvailabilityKeys[0]]: true,
            [offerAvailabilityKeys[1]]: false,
        }
        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('warning')
        expect(TimetableV2.methods.moreCourseOfferUnavailable.call(context, offeredCourses[0], moreCourse)).toBe(false)
        expect(TimetableV2.methods.moreCourseOfferDisabled.call(context, offeredCourses[0], moreCourse)).toBe(false)
        expect(TimetableV2.methods.moreCourseOfferUnavailable.call(context, offeredCourses[1], moreCourse)).toBe(true)

        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: false,
            [offerAvailabilityKeys[0]]: false,
            [offerAvailabilityKeys[1]]: false,
        }
        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('error')
        expect(TimetableV2.methods.moreCourseDisabled.call(context, moreCourse)).toBe(true)
        expect(TimetableV2.methods.moreCourseOpenDisabled.call(context, moreCourse)).toBe(false)

        context.selectedMoreCourseKey = ''
        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        expect(context.selectedMoreCourseKey).toBe(moreCourse.selectionKey)
        expect(TimetableV2.methods.moreCourseOfferDisabled.call(context, offeredCourses[0], moreCourse)).toBe(true)

        const unavailableOfferSaveCallCount = context.saveStoredTimetableState.mock.calls.length
        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, offeredCourses[0], moreCourse)

        expect(context.saveStoredTimetableState).toHaveBeenCalledTimes(unavailableOfferSaveCallCount)
    })

    it('checks more course offer availability across every category', () => {
        const courseCodes = ['BU1', 'M1', 'GW1', 'D2', 'INF2']
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'semester:D2': false,
            },
            courseGroups: courseCodes.flatMap((courseCode, courseIndex) => [
                {
                    class_name: `${courseCode}-A`,
                    course: courseCode,
                    display_label: `${courseCode} - A`,
                    hour: courseIndex + 1,
                    key: `${courseCode.toLowerCase()}-a`,
                    semester: 1,
                    teacher: 'A',
                    title: courseCode,
                    weekday: 1,
                },
                {
                    class_name: `${courseCode}-B`,
                    course: courseCode,
                    display_label: `${courseCode} - B`,
                    hour: courseIndex + 2,
                    key: `${courseCode.toLowerCase()}-b`,
                    semester: 1,
                    teacher: 'B',
                    title: courseCode,
                    weekday: 2,
                },
            ]),
            courseGroupsLoaded: true,
            selectedCourseItems: [
                { courseGroup: 'planned', key: 'D0', selectionKey: 'planned:D0' },
            ],
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 2, key: 'INF2', label: 'INF2' },
            ],
            storedCompletedCourseItems: [
                { code: 'BU1', hours: 4, key: 'BU1', label: 'BU1' },
            ],
            storedMissingCourseCardItems: [
                { code: 'M1', hours: 3, key: 'M1', label: 'M1' },
            ],
            storedPlannedCourseItems: [
                { code: 'GW1', hours: 4, key: 'GW1', label: 'GW1' },
            ],
            storedSemesterCourseItems: [
                { code: 'D2', hours: 3, key: 'D2', label: 'D2' },
            ],
        })
        const expectedPayloadGroups = {
            additional: 'additional',
            completed: 'planned',
            missing: 'missing',
            planned: 'planned',
            semester: 'planned',
        }

        Object.entries(expectedPayloadGroups).forEach(([courseGroup, expectedPayloadGroup]) => {
            const moreCourse = context.moreCoursesCardItems.find((course) => course.courseGroup === courseGroup)
            const offeredCourses = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
            const offerAvailabilityKeys = offeredCourses.map((offeredCourse) =>
                TimetableV2.methods.moreCourseOfferAvailabilityKey.call(context, moreCourse, offeredCourse))
            const offerSelectionKeys = offeredCourses.map((offeredCourse) => offeredCourse.selectionKey)

            const payload = TimetableV2.methods.timetableV2CalculationPayloadForMoreCourseAvailability.call(context, moreCourse)

            expect(offeredCourses).toHaveLength(2)
            expect(payload.candidate_courses[0]).toMatchObject({
                availability_key: moreCourse.selectionKey,
                course_group: expectedPayloadGroup,
                course_key: moreCourse.key,
            })
            expect(payload.candidate_courses.slice(1).map((candidateCourse) => candidateCourse.course_group)).toEqual([
                expectedPayloadGroup,
                expectedPayloadGroup,
            ])

            context.storedTimetableState = {
                timetableV2Selection: {
                    moreOfferedCourseSelections: Object.fromEntries(offerSelectionKeys.map((selectionKey) => [selectionKey, true])),
                },
            }
            context.moreCourseAvailabilityByKey = {
                [moreCourse.selectionKey]: true,
                [offerAvailabilityKeys[0]]: true,
                [offerAvailabilityKeys[1]]: false,
            }

            expect(TimetableV2.methods.moreCourseOfferUnavailable.call(context, offeredCourses[0], moreCourse)).toBe(false)
            expect(TimetableV2.methods.moreCourseOfferUnavailable.call(context, offeredCourses[1], moreCourse)).toBe(true)
            expect(TimetableV2.methods.moreCourseOfferDisabled.call(context, offeredCourses[1], moreCourse)).toBe(true)
            expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('warning')
            expect(TimetableV2.methods.moreOfferedCourseSelectionsForSingleCourse.call(context, moreCourse)).toEqual({
                [offerSelectionKeys[0]]: true,
            })
        })
    })

    it('uses subject row keys for synthetic completed more course availability candidates', () => {
        const context = timetableV2Context({
            courseGroups: [
                {
                    class_name: 'BU - 1 - 3C - PLA',
                    course: 'BU1',
                    display_label: 'BU - 1 - 3C - PLA',
                    hour: 14,
                    key: 'bu1-pla',
                    semester: 1,
                    teacher: 'PLA',
                    title: 'BU - 1 - 3C - PLA',
                    weekday: 1,
                },
            ],
            courseGroupsLoaded: true,
            storedCompletedCourseItems: [
                {
                    code: 'BU1',
                    hours: 4,
                    key: 'completed-BU1-1',
                    label: 'BU1',
                    meta: '1',
                },
            ],
            subjectRows: [
                {
                    branch: 'common',
                    hours_per_week: 4,
                    id: 11,
                    is_active: true,
                    json_code: 'BU1',
                    json_subject: 'BU',
                    name: 'Biologie 1',
                    semester: 1,
                },
            ],
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.courseGroup === 'completed')
        const payload = TimetableV2.methods.timetableV2CalculationPayloadForMoreCourseAvailability.call(context, moreCourse)

        expect(TimetableV2.methods.timetableV2CalculationCourseKey.call(context, moreCourse)).toBe(
            '11|1|common|BU1|BU|Biologie 1|BU1',
        )
        expect(payload.candidate_courses[0]).toMatchObject({
            availability_key: 'completed:BU1',
            course_group: 'planned',
            course_key: '11|1|common|BU1|BU|Biologie 1|BU1',
        })
    })

    it('applies the no Saturday option to timetable and availability payloads', () => {
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
            timetableCalculationResult: {
                no_saturday_timetable_count: 4,
            },
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
                    maxFreeDays: false,
                    noDistanceLearning: false,
                    noSaturday: false,
                    startsFromPeriod10: false,
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationSelectedNumber: 4,
            timetableMaxFreeDaysDraftSelected: true,
            timetableMaxFreeDaysSelected: false,
            timetableNoDistanceLearningDraftSelected: true,
            timetableNoDistanceLearningSelected: false,
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: false,
            timetableOptionsCardVisible: true,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.applyTimetableOptions.call(context)

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(context.timetableNoSaturdaySelected).toBe(true)
        expect(context.timetableMaxFreeDaysDraftSelected).toBe(true)
        expect(context.timetableMaxFreeDaysSelected).toBe(true)
        expect(context.timetableNoDistanceLearningDraftSelected).toBe(true)
        expect(context.timetableNoDistanceLearningSelected).toBe(true)
        expect(context.timetableOptionsChanged).toBe(false)
        expect(context.timetableOptionsCardVisible).toBe(false)
        expect(context.timetableCalculationSelectedNumber).toBe(1)
        expect(context.moreCourseAvailabilityByKey).toEqual({})
        expect(context.moreCourseAvailabilitySignature).toBe('')
        expect(payload.constraints.availableWeekdays).toEqual([1, 2, 3, 4, 5])
        expect(payload.selected_quality_criterion_keys).toEqual(['free_days', 'avoid_distance_learning'])
        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Options: {
                maxFreeDays: true,
                noDistanceLearning: true,
                noSaturday: true,
                startsFromPeriod10: false,
            },
        }))
        expect(calculateTimetables).toHaveBeenCalledOnce()
    })

    it('applies the starts from period 10 option to timetable constraints', () => {
        const calculateTimetables = vi.fn()
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            calculateTimetables,
            saveStoredTimetableState,
            schoolHours: [
                { hour: 8 },
                { hour: 9 },
                { hour: 10 },
                { hour: 11 },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                timetableV2Options: {
                    maxFreeDays: false,
                    noDistanceLearning: false,
                    noSaturday: false,
                    startsFromPeriod10: false,
                },
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationSelectedNumber: 3,
            timetableCalculationResult: {
                quality_counters: [
                    {
                        key: 'starts_from_period_10',
                        count: 4,
                    },
                ],
            },
            timetableOptionsCardVisible: true,
            timetableStartsFromPeriod10DraftSelected: false,
            timetableStartsFromPeriod10Selected: false,
            timetableV2Step: 'timetable-calculation',
        })

        TimetableV2.methods.toggleStartsFromPeriod10TimetableOption.call(context)
        TimetableV2.methods.applyTimetableOptions.call(context)

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.timetableStartsFromPeriod10DraftSelected).toBe(true)
        expect(context.timetableStartsFromPeriod10Selected).toBe(true)
        expect(context.timetableOptionsChanged).toBe(false)
        expect(context.timetableCalculationSelectedNumber).toBe(1)
        expect(payload.constraints.availableTimes).toEqual([10, 11])
        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Options: {
                maxFreeDays: false,
                noDistanceLearning: false,
                noSaturday: false,
                startsFromPeriod10: true,
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
                    maxFreeDays: true,
                    noDistanceLearning: true,
                    noSaturday: true,
                    startsFromPeriod10: true,
                },
                transferredStudentContext: null,
            },
            timetableNoSaturdayDraftSelected: false,
            timetableNoSaturdaySelected: false,
            timetableMaxFreeDaysDraftSelected: false,
            timetableMaxFreeDaysSelected: false,
            timetableNoDistanceLearningDraftSelected: false,
            timetableNoDistanceLearningSelected: false,
            timetableStartsFromPeriod10DraftSelected: false,
            timetableStartsFromPeriod10Selected: false,
        })

        TimetableV2.methods.syncStoredTimetableOptions.call(context)

        expect(context.timetableNoSaturdaySelected).toBe(true)
        expect(context.timetableNoSaturdayDraftSelected).toBe(true)
        expect(context.timetableMaxFreeDaysSelected).toBe(true)
        expect(context.timetableMaxFreeDaysDraftSelected).toBe(true)
        expect(context.timetableNoDistanceLearningSelected).toBe(true)
        expect(context.timetableNoDistanceLearningDraftSelected).toBe(true)
        expect(context.timetableStartsFromPeriod10Selected).toBe(true)
        expect(context.timetableStartsFromPeriod10DraftSelected).toBe(true)
        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'no-saturday',
                label: 'Kein Samstag',
            },
            {
                key: 'starts-from-period-10',
                label: 'Erst ab 10. Stunde',
            },
            {
                key: 'no-distance-learning',
                label: 'Kein Fernunterricht',
            },
            {
                key: 'max-free-days',
                label: 'Max freie Tage',
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

    it('labels compact offered courses ahead of distance learning', () => {
        const context = timetableV2Context()
        const selectedCourse = {
            hours: 2,
            key: 'M4',
            label: 'M4',
            selectionKey: 'planned:M4',
        }
        const [course] = TimetableV2.methods.uniqueOfferedCourseItems.call(context, [
            {
                code: 'M',
                isKompaktunterricht: true,
                key: 'M4-5RU',
                name: 'M4 - 5RU - SCH',
                scheduleSlots: [
                    {
                        hour: 1,
                        weekday: 1,
                    },
                ],
                semester: 1,
            },
        ], selectedCourse)

        expect(course.distanceLearning).toBe(true)
        expect(course.isKompaktunterricht).toBe(true)
        expect(TimetableV2.methods.offeredCourseInstructionLabel.call(context, course)).toBe('Kompaktunterricht')
        expect(TimetableV2.methods.selectedTimetableV2SlotInstructionLabel.call(context, {
            isDistanceLearningCourse: true,
            isKompaktunterrichtCourse: true,
        })).toBe('Kompaktunterricht')
    })

    it('shows compact timetable slots inline with their date range', () => {
        const context = timetableV2Context()
        const slot = {
            dateRangeLabel: '21.02.-25.4.',
            isKompaktunterrichtCourse: true,
            courseGroup: {
                is_kompaktunterricht: true,
            },
        }

        expect(TimetableV2.methods.selectedTimetableV2SlotTimePatternLabel.call(context, slot, { showRegularRange: true }))
            .toBe('21.02.-25.4. (Kompakt)')
        expect(TimetableV2.methods.selectedTimetableV2SlotInstructionLabel.call(context, slot)).toBe('Kompaktunterricht')
    })

    it('renders same-slot courses with the same course details and recurrence structure', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')

        expect(source).toMatch(/v-for="sameSlotEntry in selectedTimetableV2DisplaySameSlotEntries[\s\S]*class="students-timetable-v2-result-grid__code"[\s\S]*selectedTimetableV2SlotTitle\(sameSlotEntry\)[\s\S]*class="students-timetable-v2-result-grid__details"[\s\S]*selectedTimetableV2SlotDetails\(sameSlotEntry\)[\s\S]*class="students-timetable-v2-result-grid__recurrence"[\s\S]*selectedTimetableV2SlotTimePatternLabel\(sameSlotEntry, \{ showRegularRange: true \}\)/u)
    })

    it('writes block date ranges directly into each offered course day', () => {
        const context = timetableV2Context({
            schoolHours: [
                { hour: 12, from: '18:45:00', until: '19:30:00' },
                { hour: 13, from: '19:30:00', until: '20:15:00' },
            ],
        })
        const scheduleLabel = TimetableV2.methods.compactScheduleSlotsLabel.call(context, [
            {
                dateRangeLabel: '28.04. - 07.07.',
                from: '18:45',
                hour: 12,
                until: '19:30',
                weekday: 2,
            },
            {
                dateRangeLabel: '07.05. - 09.07.',
                from: '18:45',
                hour: 12,
                until: '19:30',
                weekday: 4,
            },
            {
                dateRangeLabel: '07.05. - 09.07.',
                from: '19:30',
                hour: 13,
                until: '20:15',
                weekday: 4,
            },
        ])

        expect(scheduleLabel).toBe('Di 12. 18:45-19:30 (28.04. - 07.07.), Do 12.-13. 18:45-20:15 (07.05. - 09.07.)')
    })

    it('can calculate a recommendation payload without one selected conflict course', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { courseGroup: 'missing', key: 'BU1', selectionKey: 'missing:BU1' },
                { courseGroup: 'missing', key: 'CH1', selectionKey: 'missing:CH1' },
            ],
        })
        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context, {
            excludedSelectedCourseSelectionKey: 'missing:BU1',
            includeQualityCounters: false,
            selectedTimetableNumber: 1,
            selectedTimetableType: 'full_green',
        })

        expect(payload.selected_course_keys).toEqual(['CH1'])
        expect(payload.include_quality_counters).toBe(false)
        expect(payload.selected_timetable_type).toBe('full_green')
    })

    it('includes Fernunterricht and Kompaktunterricht course variants by default', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const context = timetableV2Context({
            storedTimetableState: {
                timetableV2Selection: {},
            },
        })

        expect(source).toContain('label="Fernunterricht"')
        expect(source).toContain('label="Kompaktunterricht"')
        expect(source).toContain('v-if="courseReviewVisible && selectedCourseItemsClickable" class="students-timetable-v2-selected-courses-card__filters"')
        expect(context.includeDistanceLearningCourseVariants).toBe(true)
        expect(context.includeKompaktunterrichtCourseVariants).toBe(true)
        expect(context.offeredCourseSelected({
            selectionKey: 'course::fu',
            distanceLearning: true,
        })).toBe(true)
        expect(context.offeredCourseSelected({
            selectionKey: 'course::compact',
            isKompaktunterricht: true,
        })).toBe(true)
    })

    it('filters Fernunterricht and Kompaktunterricht course variants when unchecked', () => {
        const selectedCourse = {
            selectionKey: 'planned:INF1',
            offeredCourses: [
                {
                    selectionKey: 'planned:INF1::regular',
                    backendSelectionKey: 'regular-key',
                    distanceLearning: false,
                    isKompaktunterricht: false,
                },
                {
                    selectionKey: 'planned:INF1::fu',
                    backendSelectionKey: 'fu-key',
                    distanceLearning: true,
                },
                {
                    selectionKey: 'planned:INF1::compact',
                    backendSelectionKey: 'compact-key',
                    isKompaktunterricht: true,
                },
            ],
        }
        const context = timetableV2Context({
            selectedCourseItems: [selectedCourse],
            offeredCourseItemsForSelectedCourse: (course) => course.offeredCourses,
            storedTimetableState: {
                timetableV2Selection: {
                    includeDistanceLearningCourses: false,
                    includeKompaktunterrichtCourses: false,
                },
            },
        })

        expect(context.includeDistanceLearningCourseVariants).toBe(false)
        expect(context.includeKompaktunterrichtCourseVariants).toBe(false)
        expect(context.offeredCourseSelected(selectedCourse.offeredCourses[0])).toBe(true)
        expect(context.offeredCourseSelected(selectedCourse.offeredCourses[1])).toBe(false)
        expect(context.offeredCourseSelected(selectedCourse.offeredCourses[2])).toBe(false)
        expect(context.timetableV2DeselectedOfferedCourseGroupKeys()).toEqual(['fu-key', 'compact-key'])
    })

    it('stores unchecked instruction course filters and removes them again when checked', () => {
        const storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                includeKompaktunterrichtCourses: false,
            },
            transferredStudentContext: null,
        }
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            storedTimetableState,
            storedTimetableStateForSaving: () => storedTimetableState,
            saveStoredTimetableState,
        })

        TimetableV2.methods.updateInstructionCourseFilter.call(context, 'includeDistanceLearningCourses', false)
        TimetableV2.methods.updateInstructionCourseFilter.call(context, 'includeKompaktunterrichtCourses', true)

        expect(saveStoredTimetableState).toHaveBeenNthCalledWith(1, {
            selection: {},
            timetableV2Selection: {
                includeDistanceLearningCourses: false,
                includeKompaktunterrichtCourses: false,
            },
            transferredStudentContext: null,
        })
        expect(saveStoredTimetableState).toHaveBeenNthCalledWith(2, {
            selection: {},
            timetableV2Selection: {},
            transferredStudentContext: null,
        })
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

    it('marks selected course chips as partially selected when only some offers are selected', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const storedTimetableState = {
            timetableV2Selection: {
                offeredCourseSelections: {
                    'selected-course::regular': false,
                    'deselected-course::fu': false,
                    'deselected-course::regular': false,
                },
            },
        }
        const context = timetableV2Context({
            offeredCourseItemsForSelectedCourse: (course) => course.offeredCourses,
            storedTimetableState,
        })
        const fullySelectedCourse = {
            selectionKey: 'fully-selected-course',
            offeredCourses: [
                { selectionKey: 'fully-selected-course::fu', distanceLearning: true },
                { selectionKey: 'fully-selected-course::regular', distanceLearning: false },
            ],
        }
        const partlySelectedCourse = {
            selectionKey: 'selected-course',
            offeredCourses: [
                { selectionKey: 'selected-course::fu', distanceLearning: true },
                { selectionKey: 'selected-course::regular', distanceLearning: false },
            ],
        }
        const deselectedCourse = {
            selectionKey: 'deselected-course',
            offeredCourses: [
                { selectionKey: 'deselected-course::fu', distanceLearning: true },
                { selectionKey: 'deselected-course::regular', distanceLearning: false },
            ],
        }

        expect(source).toContain("'students-timetable-v2-selected-courses-card__course--offered-partial': selectedCourseItemOfferStatusVisible && offeredCourseItemsPartlySelected(course)")
        expect(source).toMatch(/\.students-timetable-v2-selected-courses-card__course--offered-partial \{[\s\S]*background: rgba\(255, 251, 235, 0\.96\) !important;[\s\S]*color: #92400e !important;/u)
        expect(source).toMatch(/\.students-timetable-v2-selected-courses-card__course--offered-partial\.students-timetable-v2-selected-courses-card__course--active \{[\s\S]*background: #c2410c !important;[\s\S]*color: #ffffff !important;/u)
        expect(source).toContain("'students-timetable-v2-selected-courses-card__course--offered-deselected': selectedCourseItemOfferStatusVisible && offeredCourseItemsAllDeselected(course)")
        expect(source).toMatch(/\.students-timetable-v2-selected-courses-card__course--offered-deselected \{[\s\S]*background: rgba\(254, 226, 226, 0\.96\) !important;[\s\S]*color: #991b1b !important;/u)
        expect(source).toMatch(/\.students-timetable-v2-selected-courses-card__course--offered-deselected\.students-timetable-v2-selected-courses-card__course--active \{[\s\S]*background: #991b1b !important;[\s\S]*color: #ffffff !important;/u)
        expect(source).toMatch(/\.students-timetable-v2-selected-courses-card__course--conflict \{[\s\S]*background: rgba\(254, 226, 226, 0\.98\) !important;[\s\S]*color: #7f1d1d !important;/u)
        expect(context.offeredCourseItemsPartlySelected(fullySelectedCourse)).toBe(false)
        expect(context.offeredCourseItemsAllDeselected(fullySelectedCourse)).toBe(false)
        expect(context.offeredCourseItemsPartlySelected(partlySelectedCourse)).toBe(true)
        expect(context.offeredCourseItemsAllDeselected(partlySelectedCourse)).toBe(false)
        expect(context.offeredCourseItemsPartlySelected(deselectedCourse)).toBe(false)
        expect(context.offeredCourseItemsAllDeselected(deselectedCourse)).toBe(true)
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

    it('keeps fully selected course-review chips green even when a stale timetable conflict exists', () => {
        const selectedCourse = {
            code: 'GW1',
            courseGroup: 'planned',
            key: 'GW1',
            label: 'GW1',
            selectionKey: 'planned:GW1',
            offeredCourses: [
                { selectionKey: 'planned:GW1::regular', backendSelectionKey: 'regular-key' },
                { selectionKey: 'planned:GW1::compact', backendSelectionKey: 'compact-key', isKompaktunterricht: true },
                { selectionKey: 'planned:GW1::fu', backendSelectionKey: 'fu-key', distanceLearning: true },
            ],
        }
        const context = timetableV2Context({
            courseReviewVisible: true,
            offeredCourseItemsForSelectedCourse: (course) => course.offeredCourses,
            selectedCourseItems: [selectedCourse],
            timetableV2Step: 'course-review',
            timetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '5-9': {
                            code: 'GW1',
                            conflicts: [
                                { code: 'GW1' },
                            ],
                        },
                    },
                },
            },
        })

        expect(context.offeredCourseItemsPartlySelected(selectedCourse)).toBe(false)
        expect(context.offeredCourseItemsAllDeselected(selectedCourse)).toBe(false)
        expect(TimetableV2.methods.selectedCourseItemHasConflict.call(context, selectedCourse)).toBe(false)
        expect(TimetableV2.methods.selectedCourseItemColor.call(context, selectedCourse)).toBe('success')
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
            conflictResolutionRecommendationByKey: {
                'planned:M1': true,
            },
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
            timetableCalculationVisible: true,
        })

        expect(TimetableV2.computed.selectedTimetableV2Result.call(context)?.number).toBe(1)
        expect(TimetableV2.computed.selectedTimetableV2StatusLabel.call(context)).toBe('Voller grüner Stundenplan')
        expect(TimetableV2.computed.selectedTimetableV2TitleLabel.call(context)).toBe('Stundenplan mit Überschneidung')
        expect(TimetableV2.computed.selectedTimetableV2RestartButtonInHeaderVisible.call(context)).toBe(true)
        expect(TimetableV2.computed.selectedTimetableV2Weekdays.call(context).map((weekday) => weekday.value)).toEqual([1, 2, 3, 4, 5, 6])
        expect(TimetableV2.computed.selectedTimetableV2Times.call(context)).toEqual([
            { hourLabel: '1.', timeFrom: '08:00', timeUntil: '08:50', value: 1 },
            { hourLabel: '2.', timeFrom: '08:55', timeUntil: '09:45', value: 2 },
        ])
        expect(TimetableV2.methods.selectedTimetableV2SlotTitle.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'])).toBe('INF2')
        expect(TimetableV2.methods.selectedTimetableV2SlotDetails.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'])).toBe('INF2-Grp1-FU')
        expect(context.timetableCalculationResult.selected_timetable.slots['6-2'].isDistanceLearningCourse).toBe(true)
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
            'D1-Grp1-KRO Mo 1. überschneidet sich mit M1-Grp1-MAY 3-wöchig.',
            'INF2-Grp1-FU Sa 2. überschneidet sich mit M1-Grp1-MAY 3-wöchig (10.03., 17.03.).',
        ])
        expect(context.selectedTimetableV2ConflictResolutionOptions.map((option) => ({
            actionType: option.actionType,
            buttonLabel: option.buttonLabel,
            count: option.count,
            countLabel: option.countLabel,
            label: option.label,
            recommended: option.recommended,
        }))).toEqual([
            {
                actionType: 'course',
                buttonLabel: 'M1 Entfernen · alle Konflikte',
                count: 2,
                countLabel: '2 Konflikte betroffen',
                label: 'M1',
                recommended: true,
            },
            {
                actionType: 'course',
                buttonLabel: 'D1 Entfernen · 1 Konflikt',
                count: 1,
                countLabel: '1 Konflikt betroffen',
                label: 'D1',
                recommended: false,
            },
            {
                actionType: 'course',
                buttonLabel: 'INF2 Entfernen · 1 Konflikt',
                count: 1,
                countLabel: '1 Konflikt betroffen',
                label: 'INF2',
                recommended: false,
            },
        ])

        TimetableV2.methods.applySelectedTimetableV2ConflictResolution.call(
            context,
            context.selectedTimetableV2ConflictResolutionOptions[0],
        )

        expect(context.saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                courseSelections: expect.objectContaining({
                    [context.selectedTimetableV2ConflictResolutionOptions[0].selectedCourse.selectionKey]: false,
                }),
            }),
        }))
        expect(context.calculateTimetables).toHaveBeenCalled()
    })

    it('sorts conflict summaries by weekday and hour', () => {
        const context = timetableV2Context({
            timetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '3-5': {
                            code: 'M1',
                            sourceLabel: 'M1-3A-MAY',
                            courseGroup: { weekday: 3, hour: 5, recurrence_interval: 1 },
                            conflicts: [
                                {
                                    code: 'D1',
                                    sourceLabel: 'D1-3A-GOS',
                                    courseGroup: { weekday: 3, hour: 5, recurrence_interval: 1 },
                                },
                            ],
                        },
                        '1-12': {
                            code: 'GWB1',
                            sourceLabel: 'GWB1-1C-HÖF',
                            courseGroup: { weekday: 1, hour: 12, recurrence_interval: 1 },
                            conflicts: [
                                {
                                    code: 'INF3',
                                    sourceLabel: 'INF3-8AB-MAY',
                                    courseGroup: { weekday: 1, hour: 12, recurrence_interval: 1 },
                                },
                            ],
                        },
                    },
                },
            },
        })

        expect(context.selectedTimetableV2ConflictSummaryItems).toEqual([
            'GWB1-1C-HÖF Mo 12. überschneidet sich mit INF3-8AB-MAY.',
            'M1-3A-MAY Mi 5. überschneidet sich mit D1-3A-GOS.',
        ])
    })

    it('recommends removing the full course even when alternative offers are available', () => {
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'D1-1A-KRO', course: 'D1', hour: 1, title: 'D1', weekday: 1 },
                { class_name: 'D1-2B-ALT', course: 'D1', hour: 2, title: 'D1', weekday: 2 },
                { class_name: 'M1-1A-MAY', course: 'M1', hour: 1, title: 'M1', weekday: 1 },
            ],
            conflictResolutionRecommendationByKey: {
                'planned:D1': true,
            },
            selectedCourseItems: [
                { code: 'D1', courseGroup: 'planned', key: 'D1', label: 'D1', selectionKey: 'planned:D1' },
                { code: 'M1', courseGroup: 'planned', key: 'M1', label: 'M1', selectionKey: 'planned:M1' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
            timetableCalculationResult: {
                selected_timetable: {
                    number: 1,
                    type: 'conflict',
                    slots: {
                        '1-1': {
                            code: 'D1',
                            sourceLabel: 'D1-1A-KRO',
                            courseGroup: { weekday: 1, hour: 1, recurrence_interval: 1 },
                            conflicts: [
                                {
                                    code: 'M1',
                                    sourceLabel: 'M1-1A-MAY',
                                    courseGroup: { recurrence_interval: 1 },
                                    isOccasional: false,
                                },
                            ],
                        },
                    },
                },
            },
            timetableCalculationVisible: true,
        })
        const d1Option = context.selectedTimetableV2ConflictResolutionOptions
            .find((option) => option.label === 'D1')

        expect(d1Option).toMatchObject({
            actionLabel: 'Modul entfernen',
            actionType: 'course',
            buttonLabel: 'D1 Entfernen · alle Konflikte',
            color: 'error',
            countLabel: '1 Konflikt betroffen',
            effectLabel: 'vollständig entfernen',
            icon: 'mdi-close-circle-outline',
        })

        TimetableV2.methods.applySelectedTimetableV2ConflictResolution.call(context, d1Option)

        const savedTimetableV2Selection = context.saveStoredTimetableState.mock.calls.at(-1)[0].timetableV2Selection
        expect(savedTimetableV2Selection.courseSelections).toEqual({
            'planned:D1': false,
        })
        expect(context.calculateTimetables).toHaveBeenCalled()
    })

    it('removes the full course when every alternative offer still conflicts', () => {
        const context = timetableV2Context({
            courseGroups: [
                { class_name: 'BU1-3C-PLA', course: 'BU1', hour: 2, title: 'BU1', weekday: 5 },
                { class_name: 'BU1-4A-HER', course: 'BU1', hour: 2, title: 'BU1', weekday: 5 },
                { class_name: 'GWB1-1C-HÖF', course: 'GWB1', hour: 2, title: 'GWB1', weekday: 5 },
            ],
            conflictResolutionRecommendationByKey: {
                'missing:BU1': true,
            },
            selectedCourseItems: [
                { code: 'BU1', courseGroup: 'missing', key: 'BU1', label: 'BU1', selectionKey: 'missing:BU1' },
                { code: 'GWB1', courseGroup: 'missing', key: 'GWB1', label: 'GWB1', selectionKey: 'missing:GWB1' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'missing:BU1': true,
                        'missing:GWB1': true,
                    },
                },
                transferredStudentContext: null,
            },
            timetableCalculationResult: {
                selected_timetable: {
                    number: 1,
                    type: 'conflict',
                    slots: {
                        '5-2': {
                            code: 'BU1',
                            sourceLabel: 'BU1-3C-PLA',
                            courseGroup: { weekday: 5, hour: 2, recurrence_interval: 1 },
                            conflicts: [
                                {
                                    code: 'GWB1',
                                    sourceLabel: 'GWB1-1C-HÖF',
                                    courseGroup: { weekday: 5, hour: 2, recurrence_interval: 1 },
                                    isOccasional: false,
                                },
                            ],
                        },
                    },
                },
            },
            timetableCalculationVisible: true,
        })
        const bu1Option = context.selectedTimetableV2ConflictResolutionOptions
            .find((option) => option.label === 'BU1')

        expect(bu1Option).toMatchObject({
            actionLabel: 'Modul entfernen',
            actionType: 'course',
            buttonLabel: 'BU1 Entfernen · alle Konflikte',
            color: 'error',
            effectLabel: 'vollständig entfernen',
            icon: 'mdi-close-circle-outline',
        })

        TimetableV2.methods.applySelectedTimetableV2ConflictResolution.call(context, bu1Option)

        expect(context.saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                courseSelections: {
                    'missing:GWB1': true,
                },
            }),
        }))
        expect(context.calculateTimetables).toHaveBeenCalled()
    })

    it('offers removal actions for courses without available timetable options', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                {
                    code: 'D1',
                    courseGroup: 'planned',
                    key: 'subject-row-1',
                    label: 'D1',
                    selectionKey: 'planned:subject-row-1',
                },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
            timetableCalculationResult: {
                full_green_timetable_count: 0,
                green_timetable_count: 0,
                conflict_timetable_count: 0,
                problem_courses: [
                    {
                        key: 'subject-row-1',
                        code: 'D1',
                        name: 'Deutsch 1',
                        label: 'D1 Deutsch 1',
                        reason: 'time_constraints',
                        reason_label: 'Die Zeitvorgaben schließen alle passenden Modulgruppen aus.',
                    },
                ],
                selected_timetable: null,
            },
            timetableCalculationVisible: true,
        })

        expect(context.timetableCalculationResultStatusLabel).toBe('Es wurde kein gültiger Stundenplan gefunden.')
        expect(context.timetableCalculationResultAlertType).toBe('warning')
        expect(context.timetableCalculationResultAlertIcon).toBe('mdi-calendar-alert-outline')
        expect(context.selectedTimetableV2ProblemCourseItems).toEqual([
            expect.objectContaining({
                buttonLabel: 'D1 entfernen',
                code: 'D1',
                label: 'D1',
                reason: 'time_constraints',
                reasonLabel: 'Die Zeitvorgaben schließen alle passenden Modulgruppen aus.',
                selectedCourse: context.selectedCourseItems[0],
            }),
        ])
        expect(context.selectedTimetableV2ProblemCourseActionsVisible).toBe(true)

        TimetableV2.methods.applySelectedTimetableV2ProblemCourseResolution.call(
            context,
            context.selectedTimetableV2ProblemCourseItems[0],
        )

        expect(context.saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                courseSelections: {
                    'planned:D1': false,
                    'planned:subject-row-1': false,
                },
            }),
        }))
        expect(context.calculateTimetables).toHaveBeenCalled()
    })

    it('offers course removal buttons when no timetable and no backend problem courses are available', () => {
        const chemistryCourse = {
            code: 'CH1',
            courseGroup: 'planned',
            key: 'CH1',
            label: 'CH1',
            selectionKey: 'planned:CH1',
        }
        const mathCourse = {
            code: 'M4',
            courseGroup: 'planned',
            key: 'M4',
            label: 'M4',
            selectionKey: 'planned:M4',
        }
        const context = timetableV2Context({
            offeredCourseItemsForSelectedCourse: (course) => {
                if (course.selectionKey !== chemistryCourse.selectionKey) return []

                return [
                    { selectionKey: 'planned:CH1::CH1-A' },
                    { selectionKey: 'planned:CH1::CH1-B' },
                ]
            },
            offeredCourseSelected: (course) => course.selectionKey === 'planned:CH1::CH1-A',
            selectedCourseItems: [
                chemistryCourse,
                mathCourse,
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
            timetableCalculationResult: {
                full_green_timetable_count: 0,
                green_timetable_count: 0,
                conflict_timetable_count: 0,
                selected_timetable: null,
            },
            timetableCalculationVisible: true,
        })

        expect(context.selectedTimetableV2NoResultResolutionCourseItems).toEqual([
            expect.objectContaining({
                buttonLabel: 'CH1 entfernen',
                label: 'CH1',
                selectedCourse: chemistryCourse,
            }),
        ])
        expect(context.selectedTimetableV2NoResultResolutionActionsVisible).toBe(true)

        TimetableV2.methods.applySelectedTimetableV2CourseResolution.call(
            context,
            context.selectedTimetableV2NoResultResolutionCourseItems[0],
        )

        expect(context.saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                courseSelections: {
                    'planned:CH1': false,
                },
            }),
        }))
        expect(context.calculateTimetables).toHaveBeenCalled()
    })

    it('offers option relaxation buttons when selected options leave no valid timetable', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                {
                    code: 'BU2',
                    courseGroup: 'planned',
                    key: 'BU2',
                    label: 'BU2',
                    selectionKey: 'planned:BU2',
                },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Options: {
                    maxFreeDays: true,
                    noDistanceLearning: false,
                    noSaturday: true,
                    startsFromPeriod10: false,
                },
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationResult: {
                full_green_timetable_count: 0,
                green_timetable_count: 0,
                conflict_timetable_count: 0,
                quality_counters: [
                    {
                        key: 'free_days',
                        count: 0,
                    },
                ],
                selected_timetable: null,
            },
            timetableCalculationVisible: true,
            timetableMaxFreeDaysDraftSelected: true,
            timetableMaxFreeDaysSelected: true,
            timetableNoSaturdayDraftSelected: true,
            timetableNoSaturdaySelected: true,
        })

        expect(context.selectedTimetableV2NoResultResolutionOptionItems).toEqual([
            expect.objectContaining({
                buttonLabel: 'Kein Samstag aufheben',
                key: 'no-saturday',
            }),
            expect.objectContaining({
                buttonLabel: 'Max freie Tage aufheben',
                key: 'max-free-days',
            }),
        ])

        TimetableV2.methods.applySelectedTimetableV2OptionResolution.call(
            context,
            context.selectedTimetableV2NoResultResolutionOptionItems[0],
        )

        expect(context.timetableNoSaturdaySelected).toBe(false)
        expect(context.timetableNoSaturdayDraftSelected).toBe(false)
        expect(context.timetableMaxFreeDaysSelected).toBe(true)
        expect(context.saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Options: expect.objectContaining({
                maxFreeDays: true,
                noSaturday: false,
            }),
        }))
        expect(context.calculateTimetables).toHaveBeenCalledWith({ progressContext: 'options' })
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
            'LPT-1CK-DREI Mo 1. überschneidet sich mit D1-1C-GOS (17.02.).',
            'LPT-1CK-DREI Di 1. überschneidet sich mit M1-1C-MAY (18.02.).',
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
        ).toBe('M1')
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
        )).toBe('M1')
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

    it('does not show compact same-slot courses as conflicts when their exact dates do not overlap', () => {
        const context = timetableV2Context({
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
            timetableCalculationResult: {
                selected_timetable: {
                    number: 1,
                    problems: [
                        'D5 Deutsch 5 D5-3R-SHAM überschneidet sich mit E5 Englisch 5 E5-3R-HÖF.',
                    ],
                    type: 'full_green',
                    slots: {
                        '2-14': {
                            code: 'D5',
                            sourceLabel: 'D5-3R-SHAM',
                            dateRangeLabel: '24.02.-30.6.',
                            isDistanceLearningCourse: true,
                            isKompaktunterrichtCourse: true,
                            courseGroup: {
                                weekday: 2,
                                hour: 14,
                                dates: ['2026-02-24', '2026-03-10', '2026-03-24', '2026-04-07', '2026-04-21', '2026-05-05', '2026-05-12', '2026-05-19', '2026-06-02', '2026-06-16', '2026-06-30'],
                                isKompaktunterricht: true,
                            },
                            sameSlotEntries: [
                                {
                                    code: 'E5',
                                    sourceLabel: 'E5-3R-HÖF',
                                    isKompaktunterrichtCourse: true,
                                    courseGroup: {
                                        weekday: 2,
                                        hour: 14,
                                        isKompaktunterricht: true,
                                    },
                                },
                            ],
                            conflicts: [
                                {
                                    code: 'E5',
                                    sourceLabel: 'E5-3R-HÖF',
                                    dateRangeLabel: '17.02.-14.4.',
                                    isKompaktunterrichtCourse: true,
                                    courseGroup: {
                                        weekday: 2,
                                        hour: 14,
                                        dates: ['2026-02-17', '2026-03-03', '2026-03-17', '2026-04-14'],
                                        isKompaktunterricht: true,
                                    },
                                },
                            ],
                        },
                    },
                },
            },
        })
        const slot = context.timetableCalculationResult.selected_timetable.slots['2-14']

        expect(TimetableV2.methods.selectedTimetableV2SlotInstructionLabel.call(context, slot)).toBe('Kompaktunterricht')
        expect(TimetableV2.methods.selectedTimetableV2SlotConflicts.call(context, slot)).toEqual([])
        expect(TimetableV2.methods.selectedTimetableV2RegularSameSlotConflicts.call(context, slot)).toEqual([])
        expect(TimetableV2.methods.selectedTimetableV2SlotConflictSeverity.call(context, slot)).toBe('')
        expect(TimetableV2.methods.selectedTimetableV2DisplayedSlotConflicts.call(context, slot)).toEqual([])
        expect(context.selectedTimetableV2ConflictSeverity).toBe('info')
        expect(context.selectedTimetableV2ConflictTitle).toBe('Hinweise')
        expect(context.selectedTimetableV2ConflictIcon).toBe('mdi-information-outline')
        expect(context.selectedTimetableV2ApparentOverlapSummaryItems).toEqual([
            'Di 14.: D5-3R-SHAM Termine (24.02., 10.03., 24.03., 07.04., 21.04., 05.05., 12.05., 19.05., 02.06., 16.06., 30.06.); E5-3R-HÖF Termine (17.02., 03.03., 17.03., 14.04.) - keine gleichen Termine.',
        ])
        expect(context.selectedTimetableV2ConflictSummaryItems).toEqual(context.selectedTimetableV2ApparentOverlapSummaryItems)
        expect(context.selectedTimetableV2ConflictResolutionOptions).toEqual([])
        const displaySameSlotEntries = TimetableV2.methods.selectedTimetableV2DisplaySameSlotEntries.call(context, slot)
        expect(displaySameSlotEntries).toHaveLength(1)
        expect(TimetableV2.methods.selectedTimetableV2SlotTitle.call(context, displaySameSlotEntries[0])).toBe('E5')
        expect(TimetableV2.methods.selectedTimetableV2SlotTimePatternLabel.call(context, displaySameSlotEntries[0], { showRegularRange: true }))
            .toBe('17.02.-14.4. (Kompakt)')
        expect(TimetableV2.methods.selectedTimetableV2SlotExactDateLabels.call(context, displaySameSlotEntries[0])).toEqual([
            '17.02.',
            '03.03.',
            '17.03.',
            '14.04.',
        ])
        expect(TimetableV2.methods.selectedTimetableV2CellClasses.call(context, 2, 14)).toMatchObject({
            'students-timetable-v2-result-grid__cell--conflict': false,
            'students-timetable-v2-result-grid__cell--filled': true,
        })
    })

    it('shows regular same-slot entries with overlapping date ranges as red conflicts', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { code: 'CH2', courseGroup: 'planned', key: 'CH2', label: 'CH2', selectionKey: 'planned:CH2' },
                { code: 'M5', courseGroup: 'planned', key: 'M5', label: 'M5', selectionKey: 'planned:M5' },
            ],
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
            timetableCalculationResult: {
                selected_timetable: {
                    number: 1,
                    type: 'full_green',
                    slots: {
                        '5-9': {
                            code: 'CH2',
                            sourceLabel: 'CH2-5K-PLA',
                            dateRangeLabel: '24.02.-30.6.',
                            courseGroup: {
                                weekday: 5,
                                hour: 9,
                                dates: ['2026-02-24', '2026-03-10', '2026-03-24', '2026-04-07', '2026-04-21', '2026-05-05', '2026-05-19', '2026-06-02', '2026-06-16', '2026-06-30'],
                            },
                            sameSlotEntries: [
                                {
                                    code: 'M5',
                                    sourceLabel: 'M5-3R-SCHM',
                                    dateRangeLabel: '17.02.-7.7.',
                                    courseGroup: {
                                        weekday: 5,
                                        hour: 9,
                                        dates: ['2026-02-17', '2026-03-10', '2026-03-17', '2026-03-31', '2026-04-14', '2026-04-28', '2026-05-12', '2026-05-26', '2026-06-09', '2026-06-23', '2026-07-07'],
                                    },
                                },
                            ],
                        },
                    },
                },
            },
        })
        const slot = context.timetableCalculationResult.selected_timetable.slots['5-9']

        expect(TimetableV2.methods.selectedTimetableV2SlotConflictSeverity.call(context, slot)).toBe('error')
        expect(context.selectedTimetableV2ConflictSeverity).toBe('error')
        expect(context.selectedTimetableV2HasErrorConflicts).toBe(true)
        expect(context.selectedTimetableV2ResultType).toBe('conflict')
        expect(context.timetableCalculationDisplayValidResultCount).toBe(0)
        expect(context.timetableCalculationDisplayConflictResultCount).toBe(1)
        expect(TimetableV2.methods.timetableOptionUnavailable.call(context, 'max-free-days')).toBe(true)
        expect(context.selectedTimetableV2ConflictTitle).toBe('Konflikte')
        expect(TimetableV2.methods.selectedTimetableV2DisplayedSlotConflicts.call(context, slot).map((conflict) => conflict.code)).toEqual(['M5'])
        expect(TimetableV2.methods.selectedTimetableV2CellClasses.call(context, 5, 9)).toMatchObject({
            'students-timetable-v2-result-grid__cell--conflict': true,
            'students-timetable-v2-result-grid__cell--filled': true,
        })
        expect(TimetableV2.methods.selectedCourseItemHasConflict.call(context, context.selectedCourseItems[0])).toBe(false)
        expect(TimetableV2.methods.selectedCourseItemColor.call(context, context.selectedCourseItems[0])).toBe('success')
        expect(TimetableV2.methods.selectedCourseItemHasConflict.call(context, context.selectedCourseItems[1])).toBe(false)
        expect(TimetableV2.methods.selectedCourseItemColor.call(context, context.selectedCourseItems[1])).toBe('success')
    })
})
