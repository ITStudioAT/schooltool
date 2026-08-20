import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import CourseSelectionCards from '@/pages/admin/studentsTimetables/timetableV2/CourseSelectionCards.vue'
import TimetableV2 from '@/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue'
import { readTimetableV2Source } from './TimetableV2.testSupport'

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
        calculationTimetableV2Selection: null,
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
        selectedAdaptedOfferedCourseItems: {
            get() {
                return TimetableV2.computed.selectedAdaptedOfferedCourseItems.call(context)
            },
        },
        selectedAdaptedOfferedCourseGroups: {
            get() {
                return TimetableV2.computed.selectedAdaptedOfferedCourseGroups.call(context)
            },
        },
        offerChoiceModuleItems: {
            get() {
                return TimetableV2.computed.offerChoiceModuleItems.call(context)
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
        storedAdaptedTimetableV2Selection: {
            get() {
                return TimetableV2.computed.storedAdaptedTimetableV2Selection.call(context)
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
        includeNormalunterrichtCourseVariants: {
            get() {
                return TimetableV2.computed.includeNormalunterrichtCourseVariants.call(context)
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
        moreCoursesCardItemsBySelectionKey: {
            get() {
                return TimetableV2.computed.moreCoursesCardItemsBySelectionKey.call(context)
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

    it('shows a copyable student email in the shared student summary', () => {
        const source = readTimetableV2Source()
        const studentSummarySource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableStudentSummary.vue', 'utf8')

        expect(source).toContain(':email="courseReviewStudentEmail"')
        expect(source).toContain('@copy-email="copyCourseReviewStudentEmail"')
        expect(source).toContain(':email="storedTimetableStudentEmail"')
        expect(source).toContain('@copy-email="copyStoredTimetableStudentEmail"')
        expect(studentSummarySource).toContain('@click.stop="$emit(\'copy-email\')"')
        expect(studentSummarySource).toContain('class="timetable-student-summary__email"')
        expect(studentSummarySource).toContain('E-Mail-Adresse kopieren')
        expect(studentSummarySource).toContain('mdi-content-copy')
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
        const source = readTimetableV2Source()
        const courseSelectionCardsSource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/CourseSelectionCards.vue', 'utf8')
        const printDialogSource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetablePrintDialog.vue', 'utf8')
        const studentSummarySource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableStudentSummary.vue', 'utf8')
        const stepperSource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableWizardStepper.vue', 'utf8')

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
        expect(source).not.toContain('students-timetable-v2-tests-card')
        expect(source).not.toContain('selectedTimetableV2TestsCard')
        expect(source).not.toContain("{ displayLabel: '1. D - 5 - 3R - SHAM', key: 'D5-3R-SHAM', label: 'D5-3R-SHAM' }")
        expect(source).not.toContain("label: 'Gleiche Tage/Uhrzeiten/Daten: Nein'")
        expect(source).not.toContain('{{ selectedTimetableV2SaturdayFreeTestLabel }}')
        expect(source).not.toContain('v-for="item in selectedTimetableV2TestCourseAnalysisItems"')
        expect(source).not.toContain('selectedTimetableV2TestCourseAnalysisItems')
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
        expect(source).toMatch(/v-if="courseCardsVisible && courseLimitPreselectionResetAvailable"[\s\S]*prepend-icon="mdi-tune-variant"\s+@click="applyCourseLimitPreselection\(true\)">\s+Vorauswahl zurücksetzen/u)
        expect(source).toContain(':icon="timetableCalculationLoadingIcon"')
        expect(source).toContain('{{ timetableCalculationLoadingLabel }}')
        expect(source).toContain('students-timetable-v2-card-column students-timetable-v2-selected-courses-column')
        expect(source).toContain('/api/admin/students-timetables/timetable-v2-selection-bootstrap')
        expect(source).toContain("axios.put('/api/admin/students-timetables/timetable-v2-state', { state })")
        expect(source).not.toContain('localStorage')
        expect(source).toContain('<span>Ausgewählte Angebote</span>')
        expect(source).not.toContain('<span>Ausgewählte Module</span>')
        expect(source).not.toContain('<span>Ausgewählte Angebiot</span>')
        expect(source).toMatch(/v-if="adoptedTimetableVisible && !adoptedTimetableCourseRemovalPendingVisible"[\s\S]*class="students-timetable-v2-card students-timetable-v2-more-adopted-courses-card"[\s\S]*Weitere Module/u)
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
        expect(source).toContain('v-for="card in displayedMoreCoursesCategoryCards"')
        expect(source).toContain('v-for="course in displayedActiveMoreCoursesCategoryCard.items"')
        expect(source).toContain("title: 'Abgeschlossene'")
        expect(source).toContain("title: 'Negative'")
        expect(source).toContain("title: 'Frühere'")
        expect(source).toContain("title: 'Aktuelle'")
        expect(source).toContain("title: 'Zusätzliche'")
        expect(source).not.toContain('{{ card.emptyLabel }}')
        expect(source).not.toContain('v-for="course in selectedCourseItems"')
        expect(source).not.toContain('class="students-timetable-v2-selected-courses-card__course students-timetable-v2-selected-courses-card__course--button"')
        expect(courseSelectionCardsSource).not.toContain('--schedule-accent:')
        expect(courseSelectionCardsSource).toContain('.students-timetable-v2-completed-courses__item--selected')
        expect(courseSelectionCardsSource).toContain('background: var(--schedule-accent) !important;')
        expect(courseSelectionCardsSource).toContain('border-color: var(--schedule-accent-dark) !important;')
        expect(source).toContain('<v-col v-if="selectedCourseOfferCardVisible" cols="12" class="students-timetable-v2-card-column">')
        expect(source).not.toContain('class="students-timetable-v2-course-card-footer"')
        expect(source).not.toContain('Hier können einzelne Module (z.B. Fernunterricht) abgewählt werden.')
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
        const studentStripIndex = source.indexOf('<TimetableStudentSummary')
        const stepperIndex = source.indexOf('<TimetableWizardStepper')
        const visitedCoursesCardIndex = source.indexOf('students-timetable-v2-visited-courses-card')
        const courseSelectionCardsIndex = source.indexOf('<CourseSelectionCards')

        expect(stepperIndex).toBeGreaterThan(-1)
        expect(studentStripIndex).toBeGreaterThan(stepperIndex)
        expect(visitedCoursesCardIndex).toBeGreaterThan(studentStripIndex)
        expect(courseSelectionCardsIndex).toBeGreaterThan(visitedCoursesCardIndex)
        expect(stepperSource).toContain('<nav class="timetable-wizard-stepper"')
        expect(stepperSource).toContain('v-for="(item, index) in items"')
        expect(source).toContain(':model-value="timetableV2StepNumber"')
        expect(source).toContain(':items="timetableV2StepperItems"')
        expect(stepperSource).toContain(':aria-current="item.value === modelValue ? \'step\' : undefined"')
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
        expect(source).toContain('students-timetable-v2-visited-courses-card__legend')
        expect(source).not.toContain('students-timetable-v2-visited-courses-card__negative-badge')
        expect(source).toContain('v-for="course in visitedCourseItems"')
        expect(source).toContain(':class="`students-timetable-v2-visited-courses-card__chip--${course.status}`"')
        expect(source).toContain('{{ course.grade }}')
        expect(source).toContain("this.visitedCourseItem(course, 'completed')")
        expect(source).toContain("this.visitedCourseItem(course, 'failed')")
        expect(source).toMatch(/\.students-timetable-v2-visited-courses-card__title\s*\{[\s\S]*font-size: 0\.94rem;[\s\S]*font-weight: 700;/u)
        expect(source).toMatch(/\.students-timetable-v2-visited-courses-card__list\s*\{[\s\S]*display: flex;[\s\S]*flex-wrap: wrap;/u)
        expect(source).toMatch(/\.students-timetable-v2-visited-courses-card__grade\s*\{[\s\S]*border-radius: 999px;[\s\S]*font-weight: 900;/u)
        expect(source).toContain('<CourseSelectionCards')
        expect(source).toContain(':cards="courseSelectionCards"')
        expect(source).toContain('<TimetableStudentSummary')
        expect(studentSummarySource).toContain('class="timetable-student-summary__selections"')
        expect(studentSummarySource).toContain('class="timetable-student-summary__selection-label"')
        expect(studentSummarySource).toContain('role="group"')
        expect(studentSummarySource).toContain(':aria-label="item.label"')
        expect(studentSummarySource).toContain(':title="item.label"')
        expect(studentSummarySource).toContain('class="timetable-student-summary__selection-options"')
        expect(studentSummarySource).toContain('size="small"')
        expect(studentSummarySource).toContain('density="default"')
        expect(stepperSource).toMatch(/\.timetable-wizard-stepper\s*\{[\s\S]*background: #ffffff;/u)
        expect(stepperSource).toMatch(/\.timetable-wizard-stepper__title\s*\{[\s\S]*font-size: 0\.86rem;/u)
        expect(studentSummarySource).toMatch(/\.timetable-student-summary__selection-group\s*\{[\s\S]*display: grid;/u)
        expect(studentSummarySource).toMatch(/\.timetable-student-summary__selection-option\s*\{[\s\S]*min-height: 30px;/u)
        expect(source).toMatch(/\.students-timetable-v2-selection\s*\{\s*display: grid;\s*gap: 5px;/u)
        expect(source).toMatch(/\.students-timetable-v2-selection__item\s*\{\s*display: grid;[\s\S]*grid-template-columns: minmax\(78px, 112px\) minmax\(0, 1fr\);[\s\S]*padding: 2px 0;/u)
        expect(source).toMatch(/@media \(max-width: 520px\) \{[\s\S]*\.students-timetable-v2-selection__item\s*\{[\s\S]*grid-template-columns: minmax\(0, 1fr\);/u)
        expect(source).toContain("title: 'Frühere Module'")
        expect(source).not.toContain('Alle vorgesehenen Module auswählen')
        expect(source).not.toContain('Alle vorgesehenen Module abwählen')
        expect(source).not.toContain('summaryChips')
        expect(source).toContain("title: 'Zusätzliche Module'")
        expect(courseSelectionCardsSource).toContain('{{ card.summaryLabel }}')
        expect(courseSelectionCardsSource).toContain('summaryLabel: `${selectedCourseSummary.countLabel} · ${selectedCourseSummary.hoursLabel}`')
        expect(courseSelectionCardsSource).not.toContain('summaryChips')
        expect(courseSelectionCardsSource).toContain('— zum Auswählen anklicken')
        expect(courseSelectionCardsSource).toMatch(/\.students-timetable-v2-completed-courses__item--missing \{[\s\S]*border-color: #f3b9b3;[\s\S]*color: #8f1f16;/u)
        expect(courseSelectionCardsSource).toContain('students-timetable-v2-course-card--missing')
        expect(courseSelectionCardsSource).toContain('students-timetable-v2-course-card--planned')
        expect(courseSelectionCardsSource).toContain('students-timetable-v2-course-card--semester')
        expect(courseSelectionCardsSource).toContain('students-timetable-v2-course-card--additional')
        expect(courseSelectionCardsSource).not.toContain('Die Auswahl kann später noch verändert werden!')
        expect(source).toMatch(/\.students-timetable-v2-options-card__option \{[\s\S]*background: rgba\(248, 250, 252, 0\.96\);/u)
        expect(source).toMatch(/\.students-timetable-v2-options-card__option--unavailable \{[\s\S]*background: rgba\(241, 245, 249, 0\.96\);/u)
        expect(source).toMatch(/\.students-timetable-v2-options-card__option--loading \{[\s\S]*background: rgba\(248, 250, 252, 0\.98\);/u)
        expect(source).not.toContain('v-else-if="timetableCalculationVisible" class="students-timetable-v2-calculation-card__more-course-actions"')
        expect(source).toMatch(/class="students-timetable-v2-more-courses-card__header-cancel"[\s\S]*@click="cancelMoreCoursesCard">\s+Abbruch/u)
        expect(source).toMatch(/size="large"\s+color="warning"\s+variant="tonal"\s+prepend-icon="mdi-close"\s+@click="cancelMoreCoursesCard">\s+Abbruch/u)
        expect(source).toMatch(/v-else-if="adoptedTimetableCourseRemovalPendingVisible"[\s\S]*@click="cancelMoreCoursesCard">\s+Abbruch[\s\S]*color="error"\s+variant="flat"\s+prepend-icon="mdi-delete"\s+:disabled="!moreCoursesSelectionChanged"\s+@click="applyAdoptedTimetableCourseRemoval">\s+Löschen/u)
        expect(source).toContain('<span>{{ timetableCalculationCardTitleLabel }}</span>')
        expect(source).not.toContain('v-else-if="adoptedTimetableVisible" class="students-timetable-v2-calculation-card__actions"')
        expect(source).toMatch(/class="students-timetable-v2-result__meta"[\s\S]*prepend-icon="mdi-printer-outline"[\s\S]*:loading="pdfExporting"[\s\S]*@click="openAdoptedTimetablePrintDialog">\s+Drucken[\s\S]*v-if="adoptedTimetableSaveVisible"[\s\S]*prepend-icon="mdi-content-save-outline"[\s\S]*:loading="publishedTimetableSaving"[\s\S]*@click="saveAdoptedPublishedStudentTimetable"[\s\S]*Speichern für[\s\S]*prepend-icon="mdi-arrow-left"[\s\S]*Zurück/u)
        expect(source).not.toContain('{{ adoptedTimetableNumberLabel }}')
        expect(source).not.toContain('prepend-icon="mdi-check">')
        expect(source).toMatch(/<TimetablePrintDialog[\s\S]*v-model="printDialogVisible"[\s\S]*@print="downloadAdoptedTimetablePdf"/u)
        expect(printDialogSource).toMatch(/<v-dialog[\s\S]*Stundenplan drucken[\s\S]*label="Einzelne Wochen drucken"[\s\S]*label="Modulliste"[\s\S]*label="Modulübersicht"[\s\S]*@click="\$emit\('print'\)">[\s\S]*Drucken/u)
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
    })

    it('adds A or B to two-week recurrence labels when all dates use one ISO week parity', () => {
        const context = timetableV2Context()

        expect(context.selectedTimetableV2SlotRecurrenceLabel({
            courseGroup: {
                dates: ['2026-02-16', '2026-03-02'],
                recurrence_interval: 2,
            },
        })).toBe('2-wöchig A')
        expect(context.selectedTimetableV2SlotRecurrenceLabel({
            courseGroup: {
                dates: ['2026-02-23', '2026-03-09'],
                recurrence_interval: 2,
            },
        })).toBe('2-wöchig B')
        expect(context.selectedTimetableV2SlotRecurrenceLabel({
            courseGroup: {
                dates: ['2026-02-16', '2026-02-23'],
                recurrence_interval: 2,
            },
        })).toBe('2-wöchig')
        expect(context.offeredCourseScheduleRecurrenceLabel({
            dates: ['2026-02-23', '2026-03-09'],
            recurrence_interval: 2,
        })).toBe('2-wöchig B')
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

    it('keeps only the most recently staged timetable option selected', () => {
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

        expect(context.timetableNoSaturdayDraftSelected).toBe(false)
        expect(context.timetableStartsFromPeriod10DraftSelected).toBe(true)
        expect(context.timetableMaxFreeDaysSelected).toBe(false)
        expect(context.timetableNoDistanceLearningSelected).toBe(false)
        expect(calculateTimetables).not.toHaveBeenCalled()

        TimetableV2.methods.toggleMaxFreeDaysTimetableOption.call(context)

        expect(context.timetableNoSaturdayDraftSelected).toBe(false)
        expect(context.timetableStartsFromPeriod10DraftSelected).toBe(false)
        expect(context.timetableStartsFromPeriod10Selected).toBe(false)
        expect(context.timetableMaxFreeDaysDraftSelected).toBe(true)
        expect(context.timetableMaxFreeDaysSelected).toBe(false)
        expect(calculateTimetables).not.toHaveBeenCalled()

        TimetableV2.methods.toggleNoDistanceLearningTimetableOption.call(context)

        expect(context.timetableMaxFreeDaysDraftSelected).toBe(false)
        expect(context.timetableMaxFreeDaysSelected).toBe(false)
        expect(context.timetableNoDistanceLearningDraftSelected).toBe(true)
        expect(context.timetableNoDistanceLearningSelected).toBe(false)
        expect(context.selectedTimetableOptionItems).toEqual([
            {
                key: 'no-distance-learning',
                label: 'Kein Fernunterricht',
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
        const source = readTimetableV2Source()

        expect(source).toContain('class="students-timetable-v2-card students-timetable-v2-selected-options-card"')
        expect(source).toContain('v-if="selectedTimetableOptionsSummaryCardVisible"')
        expect(source).toContain('Ausgewählte Optionen')
        expect(source).toContain('v-for="option in selectedTimetableOptionItems"')
        expect(source).toContain(':closable="selectedTimetableOptionItemsDeletable"')
        expect(source).toContain('@click:close.stop="removeSelectedTimetableOption(option)"')
        expect(source).toContain('Keine Optionen ausgewählt')
        expect(source).toContain('class="students-timetable-v2-selected-options-card__title students-timetable-v2-offerchoices__title"')
        expect(source.indexOf('Gewählte Module')).toBeLessThan(source.indexOf('Ausgewählte Optionen'))
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

        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            adaptedTimetableV2Selection: initialTimetableV2Selection,
            timetableV2Options: {
                maxFreeDays: false,
                noDistanceLearning: false,
                noSaturday: false,
                startsFromPeriod10: false,
            },
            transferredStudentContext: null,
        }))
        expect(context.calculationTimetableV2Selection).toEqual(initialTimetableV2Selection)
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

    it('renders more course options inline with distinct neutral, selected, and conflict states', () => {
        const source = readTimetableV2Source()

        expect(source).not.toContain('<v-checkbox-btn')
        expect(source).toContain('class="students-timetable-v2-more-courses-section"')
        expect(source).toContain('class="students-timetable-v2-more-courses-section__panel"')
        expect(source).not.toContain('class="students-timetable-v2-more-courses-overlay"')
        expect(source).not.toContain('aria-modal="true"')
        expect(source).not.toContain('v-else-if="timetableCalculationVisible" class="students-timetable-v2-calculation-card__more-course-actions"')
        expect(source).toMatch(/class="students-timetable-v2-more-courses-card__header-back"[\s\S]*:disabled="!!selectedMoreCourseItem"[\s\S]*>\s+Zurück/u)
        expect(source).toMatch(/class="students-timetable-v2-more-courses-card__header-cancel"[\s\S]*:disabled="!!selectedMoreCourseItem"[\s\S]*>\s+Abbruch/u)
        expect(source).not.toContain('class="students-timetable-v2-more-courses-card__warning-badge"')
        expect(source).not.toMatch(/class="students-timetable-v2-more-courses-card__course"[\s\S]*:title="course\.title"[\s\S]*@click="toggleMoreCourseOffers\(course\)"/u)
        expect(source).toMatch(/class="students-timetable-v2-more-courses-card__offer-count"[\s\S]*\{\{ course\.offerCountLabel \}\}/u)
        expect(source).toMatch(/class="students-timetable-v2-more-courses-card__label">[\s\S]*\{\{ course\.label \}\}[\s\S]*v-if="course\.status === 'flagged'"[\s\S]*class="students-timetable-v2-more-courses-card__warning">[\s\S]*\{\{ course\.warningLabel \}\}/u)
        expect(source).toContain("course.status === 'flagged'")
        expect(source).toContain('`students-timetable-v2-more-courses-card__course--${status}`')
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__list \{[\s\S]*grid-template-columns: repeat\(auto-fill, 170px\);[\s\S]*justify-content: start;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course \{[\s\S]*width: 170px;[\s\S]*height: 130px;[\s\S]*box-sizing: border-box;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__label \{[\s\S]*padding-right: 28px;[\s\S]*text-align: left;[\s\S]*white-space: nowrap;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__warning \{[\s\S]*grid-template-columns: 14px minmax\(0, 1fr\);[\s\S]*text-align: left;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course--neutral,[\s\S]*border-color: #86efac;[\s\S]*background: #f0fdf4;[\s\S]*color: #166534;/u)
        expect(source).not.toContain('.students-timetable-v2-more-courses-card__course--selected')
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course--flagged,[\s\S]*border-color: #f3b9b3;[\s\S]*background: #fef2f2;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course--warning\.students-timetable-v2-more-courses-card__course--flagged,[\s\S]*border-color: #fb923c;[\s\S]*background: #fff7ed;[\s\S]*color: #c2410c;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course--no-offers,[\s\S]*border-color: #cbd5e1;[\s\S]*background: #f8fafc;[\s\S]*color: #334155;[\s\S]*opacity: 1;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course--no-offers \.students-timetable-v2-more-courses-card__warning \{[\s\S]*color: #475569;[\s\S]*font-size: 0\.75rem;[\s\S]*font-weight: 800;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-courses-card__course--disabled \{[\s\S]*opacity: 0\.48;[\s\S]*transform: none !important;/u)
        expect(source).toMatch(/@click="toggleMoreCourseOffers\(course\)"/u)
    })

    it('disables more-course cards without offers and shows the correct diagnosis', () => {
        const course = {
            label: 'LPT',
            selectionKey: 'additional:LPT',
        }
        const context = {
            moreCourseAvailabilityKey: () => course.selectionKey,
            moreCourseChipColorWithContext: () => 'error',
            moreCourseRestorableWithContext: () => false,
            moreCourseSelectionLockedWithContext: () => false,
            moreCourseUnavailableWithContext: () => true,
            moreCourseOfferUnavailableWithContext: () => true,
            moreOfferedCourseSelectedWithContext: () => false,
            offeredCourseItemsForSelectedCourse: () => [],
        }

        const displayedCourse = TimetableV2.methods.displayedMoreCourseCardItem.call(context, course, {})

        expect(displayedCourse.disabled).toBe(true)
        expect(displayedCourse.ariaDisabled).toBe('true')
        expect(displayedCourse.noOffers).toBe(true)
        expect(displayedCourse.offerCountLabel).toBe('0/0')
        expect(displayedCourse.selectedOfferCount).toBe(0)
        expect(displayedCourse.warningLabel).toBe('Keine Angebote gefunden')
        expect(displayedCourse.classes[2]).toEqual(expect.objectContaining({
            'students-timetable-v2-more-courses-card__course--disabled': true,
            'students-timetable-v2-more-courses-card__course--no-offers': true,
        }))

        context.moreCourseAvailabilityLoading = false
        context.moreCourseSelectionLocked = () => false

        expect(TimetableV2.methods.moreCourseOpenDisabled.call(context, course)).toBe(true)
    })

    it('uses the shared handoff tokens for module review and timetable calculation', () => {
        const source = readTimetableV2Source()

        expect(source).toContain('class="students-timetable-v2-selected-offers-card__group-title"')
        expect(source).toContain('class="students-timetable-v2-selected-offers-card__count"')
        expect(source).toContain('students-timetable-v2-selected-offers-card__instruction--compact')
        expect(source).toContain('students-timetable-v2-selected-offers-card__instruction--distance-learning')
        expect(source).toContain('class="students-timetable-v2-calculation-card__adopt-button"')
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__adopt-button \{[\s\S]*background: #0f766e !important;/u)
        expect(source).toMatch(/\.students-timetable-v2-result-grid__cell--header \{[\s\S]*background: #eef1ff;[\s\S]*color: #4338ca;/u)
        expect(source).toMatch(/\.students-timetable-v2-result-grid__cell--filled \{[\s\S]*background: rgba\(220, 252, 231, 0\.94\);/u)
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

    it('opens more course offers and ensures offer availability is checked', () => {
        const requestMoreCourseAvailability = vi.fn(() => Promise.resolve({
            data: {
                data: {
                    availability: {},
                },
            },
        }))
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
        expect(requestMoreCourseAvailability).toHaveBeenCalledWith([moreCourse], { includeOffers: true })
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
        const source = readTimetableV2Source()

        expect(source).toMatch(/class="students-timetable-v2-offered-courses-card__actions"[\s\S]*v-if="selectedMoreCourseHasSelectedOffers"[\s\S]*append-icon="mdi-check"[\s\S]*:disabled="!moreCoursesSelectionChanged \|\| timetableCalculationLoading"[\s\S]*@click\.stop="applyMoreCoursesSelection">\s+Anwenden[\s\S]*@click\.stop="closeMoreCourseOffers">\s+Schließen/u)
        expect(source).toMatch(/class="students-timetable-v2-offered-courses-card__actions"[\s\S]*size="small"[\s\S]*color="success"[\s\S]*variant="tonal"[\s\S]*append-icon="mdi-check"/u)
        expect(source).toContain('prepend-icon="mdi-close"')
        expect(source).toMatch(/prepend-icon="mdi-close"[\s\S]*color="error"[\s\S]*aria-label="Schließen"/u)
        expect(source).toContain('aria-label="Schließen"')
        expect(source).toMatch(/@click\.stop="closeMoreCourseOffers">\s+Schließen/u)
        expect(source).toMatch(/@click\.stop="closeMoreCourseOffers"/u)
        expect(source).toContain(':class="course.classes"')
        expect(source).toContain(':aria-disabled="course.ariaDisabled"')
        expect(source).toContain('@click="toggleMoreOfferedCourseItem(course, selectedMoreCourseItem)"')
        expect(source).toContain('v-if="course.conflictItems.length"')
        expect(source).toContain('class="students-timetable-v2-offered-courses-card__conflicts"')
        expect(source).toContain('v-for="conflict in course.conflictItems"')
        expect(source).toContain('{{ conflict.courseLabels.join(\', \') }}')
    })

    it('shows conflict details below unavailable more course offers', () => {
        const context = timetableV2Context({
            courseGroups: [
                {
                    class_name: 'D5-3R-SHAM',
                    course: 'D',
                    dates: ['2026-02-17'],
                    ends_at: '21:10',
                    hour: 14,
                    module_code: 'D5',
                    starts_at: '20:25',
                    title: 'D5',
                    weekday: 2,
                },
            ],
            courseSelectionOverrides: {
                'semester:D5': false,
            },
            selectedMoreCourseKey: 'semester:D5',
            storedSemesterCourseItems: [
                { code: 'D5', hours: 3, key: 'D5', label: 'D5' },
            ],
            timetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '2-14': {
                            code: 'E5',
                            sourceLabel: 'E5-3R-HÖF',
                            courseGroup: {
                                class_name: 'E5-3R-HÖF',
                                dates: ['2026-02-17'],
                                hour: 14,
                                weekday: 2,
                            },
                        },
                    },
                },
            },
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.label === 'D5')
        const offeredCourse = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)[0]
        const availabilityKey = TimetableV2.methods.moreCourseOfferAvailabilityKey.call(context, moreCourse, offeredCourse)
        context.moreCourseAvailabilityByKey = {
            [availabilityKey]: false,
        }

        const [displayedOffer] = context.selectedMoreCourseOfferedCourseItems

        expect(displayedOffer.unavailable).toBe(true)
        expect(displayedOffer.conflictItems).toEqual([
            expect.objectContaining({
                courseLabels: ['E5-3R-HÖF'],
                dateLabel: expect.stringContaining('17.02.'),
                timeLabel: 'Di 14. 20:25-21:10',
            }),
        ])
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
        const source = readTimetableV2Source()
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
        const source = readTimetableV2Source()
        const courseSelectionCardsSource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/CourseSelectionCards.vue', 'utf8')
        const additionalCourse = { code: 'INF2', hours: 4, label: 'INF2' }
        const saveStoredTimetableState = vi.fn()

        expect(source).toContain("items: this.storedAdditionalCourseItems.map((course) => this.courseSelectionCardItem(course, 'additional'))")
        expect(source).not.toContain('courseSelectionCardSummaryChips')
        expect(source).toContain("toggleCourseSelectionCardItem(courseItem)")
        expect(courseSelectionCardsSource).toContain('v-for="course in card.items"')
        expect(courseSelectionCardsSource).toContain('variant="flat"')
        expect(courseSelectionCardsSource).toContain(':class="course.classes"')
        expect(courseSelectionCardsSource).toContain('@click="toggleCourse(course)"')
        expect(courseSelectionCardsSource).toContain('students-timetable-v2-completed-courses__item--toggle')
        expect(courseSelectionCardsSource).toMatch(/\.students-timetable-v2-completed-courses__item--additional \{[\s\S]*border-color: #c7c9f5;[\s\S]*color: #4338ca;/u)
        expect(courseSelectionCardsSource).toContain("emits: ['apply-course-selections']")
        expect(courseSelectionCardsSource).toContain("this.$emit('apply-course-selections', this.activeCourseSelections)")
        expect(courseSelectionCardsSource).not.toContain('courseSelectionDraftChanged')
        expect(courseSelectionCardsSource).not.toContain('students-timetable-v2-course-selection-draft-action')
        expect(courseSelectionCardsSource).not.toContain('Übernehmen')
        expect(courseSelectionCardsSource).not.toContain('Abbruch')

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
        const source = readTimetableV2Source()
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
        expect(source).not.toContain('courseSelectionCardSummaryChips')
        expect(courseSelectionCardsSource).toContain('v-for="course in card.items"')
        expect(courseSelectionCardsSource).toContain(':md="card.mdColumns"')
        expect(courseSelectionCardsSource).toContain('mdColumns: card.mdColumns || this.courseCardMdColumns')
        expect(courseSelectionCardsSource).toContain("card.courseGroup === 'completed'")
        expect(courseSelectionCardsSource).toContain('— zum Auswählen anklicken')
        expect(courseSelectionCardsSource).toContain('students-timetable-v2-card-column--completed')
        expect(courseSelectionCardsSource).toContain('border: 1.5px dashed #c7cbd6;')
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

    it('keeps shared schedule tokens on the page root and removes dead redesign hooks', () => {
        const shellSource = readFileSync('resources/js/pages/admin/studentsTimetables/StudentsTimetables.vue', 'utf8')
        const timetableSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue',
            'utf8',
        )
        const courseSelectionCardsSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV2/CourseSelectionCards.vue',
            'utf8',
        )
        const redesignSources = `${shellSource}\n${timetableSource}\n${courseSelectionCardsSource}`

        expect(shellSource).toContain('.students-timetables-page {')
        expect(redesignSources.match(/--schedule-accent:/gu)).toHaveLength(1)
        expect(redesignSources.match(/--schedule-accent-dark:/gu)).toHaveLength(1)
        expect(redesignSources.match(/--schedule-border:/gu)).toHaveLength(1)
        expect(redesignSources.match(/--schedule-heading:/gu)).toHaveLength(1)
        expect(redesignSources.match(/--schedule-muted:/gu)).toHaveLength(1)
        expect(shellSource).not.toContain('st-nav__button-icon')
        expect(shellSource).not.toContain('st-nav__button-meta')
        expect(timetableSource).not.toContain('students-timetable-v2-page--course-selection')
        expect(courseSelectionCardsSource).not.toContain('students-timetable-v2-card-column--status')
        expect(courseSelectionCardsSource).not.toContain('courseColor(')
        expect(courseSelectionCardsSource).not.toContain('courseVariant(')
    })

    it('builds one course-limit summary while displaying all course chips', () => {
        const selectedCourse = {
            courseGroup: 'planned',
            defaultSelected: false,
            hoursNumber: 3,
            key: 'planned-D1',
            label: 'D1',
            selectionKey: 'planned:D1',
            unavailable: false,
        }
        const candidateCourse = {
            courseGroup: 'planned',
            defaultSelected: false,
            hoursNumber: 4,
            key: 'planned-M1',
            label: 'M1',
            selectionKey: 'planned:M1',
            unavailable: false,
        }
        const context = {
            ...CourseSelectionCards.methods,
            cards: [
                {
                    courseGroup: 'planned',
                    items: [selectedCourse, candidateCourse],
                    key: 'planned',
                    title: 'Frühere Module',
                    visible: true,
                },
            ],
            courseCardMdColumns: 3,
            courseSelections: {
                'planned:D1': true,
            },
            draftCourseSelections: null,
        }

        Object.defineProperties(context, {
            activeCourseSelections: {
                get() {
                    return CourseSelectionCards.computed.activeCourseSelections.call(context)
                },
            },
            visibleCards: {
                get() {
                    return CourseSelectionCards.computed.visibleCards.call(context)
                },
            },
        })

        context.courseSelectionLimitSummary = vi.fn((courseSelections) => (
            CourseSelectionCards.methods.courseSelectionLimitSummary.call(context, courseSelections)
        ))

        const displayedCards = CourseSelectionCards.computed.displayedCards.call(context)

        expect(context.courseSelectionLimitSummary).toHaveBeenCalledOnce()
        expect(displayedCards[0].summaryLabel).toBe('1 Modul · 3 Std.')
        expect(displayedCards[0].items[0]).not.toHaveProperty('color')
        expect(displayedCards[0].items[0]).not.toHaveProperty('variant')
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
        expect(context.$emit).toHaveBeenCalledWith('apply-course-selections', {
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
            visibleCards: {
                get() {
                    return CourseSelectionCards.computed.visibleCards.call(context)
                },
            },
        })

        expect(CourseSelectionCards.methods.courseSelected.call(context, semesterCourse)).toBe(false)

        CourseSelectionCards.methods.toggleCourse.call(context, semesterCourse)

        expect(context.draftCourseSelections).toEqual({})
        expect(context.$emit).toHaveBeenCalledWith('apply-course-selections', {})
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
            grade: course.grade,
            label: course.label,
            status: course.status,
        }))).toEqual([
            {
                grade: '5',
                label: 'AM1',
                status: 'failed',
            },
            {
                grade: '3',
                label: 'BU1',
                status: 'completed',
            },
            {
                grade: 'N',
                label: 'CH2',
                status: 'failed',
            },
            {
                grade: '4',
                label: 'D2',
                status: 'completed',
            },
        ])

        const source = readTimetableV2Source()

        expect(source).toContain('students-timetable-v2-visited-courses-card__legend')
        expect(source).toContain('.students-timetable-v2-visited-courses-card__chip--failed')
        expect(source).not.toContain('students-timetable-v2-visited-courses-card__negative-badge')
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
        const source = readTimetableV2Source()
        const summarySource = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableStudentSummary.vue', 'utf8')
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
        const languageSummaryItem = context.storedTimetableSelectionSummary.find((item) => item.key === 'language')
        const branchSummaryItem = context.storedTimetableSelectionSummary.find((item) => item.key === 'branch')
        const artsSummaryItem = context.storedTimetableSelectionSummary.find((item) => item.key === 'artsSubject')

        expect(summarySource).toContain('class="timetable-student-summary__religion"')
        expect(summarySource).toContain('class="timetable-student-summary__email"')
        expect(summarySource).toContain('grid-template-columns: repeat(5, minmax(0, 1fr));')
        expect(source).toContain(':religion-meta="storedTimetableStudentReligionMeta()"')
        expect(TimetableV2.computed.storedTimetableStudentLabel.call(context)).toBe('5K · SOLLEDER Luis · Semester 5')
        expect(TimetableV2.computed.storedTimetableStudentEmail.call(context)).toBe('luis.solleder@example.test')
        expect(TimetableV2.computed.storedTimetableStudentEmail.call(fallbackEmailContext)).toBe('stale.context@example.test')
        expect(TimetableV2.methods.storedTimetableStudentReligionMeta.call(context)).toBe('Religion: Rk')
        expect(religionSummaryItem).toMatchObject({
            key: 'religion',
            label: 'Ethik / Religion',
        })
        expect(religionSummaryItem?.options.map((option) => option.value)).toEqual(['ETH', 'Rk'])
        expect(religionSummaryItem?.options.map((option) => option.title)).toEqual(['ETH', 'Rk'])
        expect(languageSummaryItem?.options.map((option) => option.title)).toEqual(['L', 'F', 'SPA'])
        expect(branchSummaryItem?.options.map((option) => option.title)).toEqual(['WIKU', 'GYM'])
        expect(artsSummaryItem?.options.map((option) => option.title)).toEqual(['ME', 'BE'])
    })

    it('shows passed retry courses as completed with the grade trend', () => {
        const source = readTimetableV2Source()
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
                        { code: 'M2', grade: '2' },
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

    it('keeps explicitly selected additional modules in the course review when the prerequisite is completed', () => {
        let context: ReturnType<typeof timetableV2Context>
        const transferredStudentContext = {
            student: {
                religion: 'evang. A.B.',
                semesterLabel: 'Semester 5',
            },
            courses: {
                completed: [
                    { code: 'E1', grade: 'B' },
                    { code: 'E2', grade: 'B' },
                    { code: 'E3', grade: '4' },
                    { code: 'E4', grade: '3' },
                    { code: 'M1', grade: 'B' },
                    { code: 'M2', grade: 'B' },
                    { code: 'M3', grade: '3' },
                ],
                failed: [],
                missing: [],
                planned: [
                    { code: 'M4', hours: 3, label: 'M4', semester: 4 },
                ],
                additional: [
                    { code: 'E6', hours: 4, label: 'E6', semester: 6 },
                ],
            },
        }
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            courseCardsVisible: true,
            saveStoredTimetableState,
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    language: 'F',
                    religion: 'Rev',
                    semester: 5,
                    courseSelections: {
                        'additional:E6': true,
                        'planned:M4': true,
                        'semester:M5': true,
                    },
                },
                transferredStudentContext,
            },
            subjectRows: [
                { id: 1, semester: 4, branch: 'common', json_code: 'M4', json_subject: 'M', name: 'Mathematik 4', hours_per_week: 3 },
                { id: 2, semester: 5, branch: 'common', json_code: 'E5', json_subject: 'E', name: 'Englisch 5', hours_per_week: 3 },
                { id: 3, semester: 5, branch: 'common', json_code: 'M5', json_subject: 'M', name: 'Mathematik 5', hours_per_week: 3 },
                { id: 4, semester: 6, branch: 'common', json_code: 'E6', json_subject: 'E', name: 'Englisch 6', hours_per_week: 4 },
            ],
        })

        Object.defineProperty(context, 'storedTimetableStudentContext', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedTimetableStudentContext.call(context)
            },
        })
        Object.defineProperty(context, 'storedAdditionalCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedAdditionalCourseItems.call(context)
            },
        })

        expect(context.storedAdditionalCourseItems.map((course) => course.code)).toContain('E6')
        expect(context.selectedAdditionalCourseItems.map((course) => course.code)).toEqual(['E6'])
        expect(context.selectedCourseItems.map((course) => course.code)).toEqual(['E5', 'E6', 'M4', 'M5'])

        TimetableV2.methods.openCourseReview.call(context)

        expect(saveStoredTimetableState).toHaveBeenCalledOnce()
        expect(context.storedTimetableState.adaptedTimetableV2Selection.courseSelections).toEqual({
            'additional:E6': true,
            'planned:M4': true,
            'semester:M5': true,
        })
        expect(context.selectedCourseItems.map((course) => course.code)).toEqual(['E5'])
        expect(context.timetableV2Step).toBe('course-review')
    })

    it('keeps explicitly selected additional module chips when the additional course bucket is stale', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    religion: 'evang. A.B.',
                    semesterLabel: 'Semester 5',
                },
                courses: {
                    completed: [
                        { code: 'E1', grade: 'B' },
                        { code: 'E2', grade: 'B' },
                        { code: 'E3', grade: '4' },
                        { code: 'E4', grade: '3' },
                        { code: 'M1', grade: 'B' },
                        { code: 'M2', grade: 'B' },
                        { code: 'M3', grade: '3' },
                    ],
                    failed: [],
                    missing: [],
                    planned: [
                        { code: 'M4', hours: 3, label: 'M4', semester: 4 },
                    ],
                    additional: [],
                },
            },
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    language: 'F',
                    religion: 'Rev',
                    semester: 5,
                    courseSelections: {
                        'additional:E6': true,
                        'planned:M4': true,
                        'semester:M5': true,
                    },
                },
                transferredStudentContext: null,
            },
            subjectRows: [
                { id: 1, semester: 4, branch: 'common', json_code: 'M4', json_subject: 'M', name: 'Mathematik 4', hours_per_week: 3 },
                { id: 2, semester: 5, branch: 'common', json_code: 'E5', json_subject: 'E', name: 'Englisch 5', hours_per_week: 3 },
                { id: 3, semester: 5, branch: 'common', json_code: 'M5', json_subject: 'M', name: 'Mathematik 5', hours_per_week: 3 },
                { id: 4, semester: 6, branch: 'common', json_code: 'E6', json_subject: 'E', name: 'Englisch 6', hours_per_week: 4 },
            ],
        })

        Object.defineProperty(context, 'storedAdditionalCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedAdditionalCourseItems.call(context)
            },
        })

        expect(context.storedAdditionalCourseItems.map((course) => course.code)).toEqual([])
        expect(context.selectedCourseItems.map((course) => course.code)).toEqual(['E5', 'E6', 'M4', 'M5'])
        expect(context.selectedCourseItems.find((course) => course.code === 'E6')).toMatchObject({
            label: 'E6',
            meta: '4 Std.',
        })
    })

    it('keeps explicitly selected current module chips when the module is present in the calculated timetable', () => {
        const context = timetableV2Context({
            storedAdditionalCourseItems: [
                { code: 'E6', hours: 4, key: 'E6', label: 'E6' },
            ],
            storedPlannedCourseItems: [
                { code: 'M4', hours: 3, key: 'M4', label: 'M4' },
            ],
            storedSemesterCourseItems: [
                { code: 'E5', hours: 3, key: 'E5', label: 'E5' },
                { code: 'M5', hours: 3, key: 'M5', label: 'M5' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'additional:E6': true,
                        'planned:M4': true,
                        'semester:D5': true,
                        'semester:M5': true,
                    },
                },
                transferredStudentContext: null,
            },
            subjectRows: [
                { id: 1, semester: 5, branch: 'common', json_code: 'D5', json_subject: 'D', name: 'Deutsch 5', hours_per_week: 3 },
            ],
            timetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '5-1': {
                            code: 'D5',
                            courseGroup: {
                                course: 'D5',
                                hour: 1,
                                title: 'D5',
                                weekday: 5,
                            },
                            name: 'D5',
                        },
                    },
                },
            },
            timetableCalculationVisible: true,
        })

        expect(context.selectedTimetableV2CourseCodes.has('D5')).toBe(true)
        expect(context.selectedCourseItems.map((course) => course.code)).toEqual(['D5', 'E5', 'E6', 'M4', 'M5'])
        expect(context.selectedCourseItems.find((course) => course.code === 'D5')).toMatchObject({
            label: 'D5',
            meta: '3 Std.',
        })
    })

    it('does not preselect a current module when the previous module is missing', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 5',
                },
                courses: {
                    completed: [],
                    failed: [
                        { code: 'CH1', grade: '5' },
                    ],
                    missing: [
                        { code: 'CH1', hours: 3, label: 'CH1' },
                    ],
                    planned: [],
                },
            },
            subjectRows: [
                {
                    id: 1,
                    semester: 5,
                    branch: 'common',
                    json_code: 'CH2',
                    json_subject: 'CH',
                    name: 'Chemie 2',
                    hours_per_week: 3,
                },
            ],
        })

        const ch2Course = context.storedSemesterCourseItems[0]
        const ch2CardItem = TimetableV2.methods.courseSelectionCardItem.call(context, ch2Course, 'semester')

        expect(context.storedSemesterCourseItems.map((course) => course.code)).toEqual(['CH2'])
        expect(TimetableV2.methods.courseItemUnavailable.call(context, ch2Course, 'semester')).toBe(false)
        expect(TimetableV2.methods.courseItemDefaultSelected.call(context, ch2Course, 'semester')).toBe(false)
        expect(TimetableV2.methods.courseItemSelected.call(context, ch2Course, 'semester')).toBe(false)
        expect(ch2CardItem.color).toBe('success')
        expect(ch2CardItem.selected).toBe(false)
    })

    it('marks current modules unavailable when the completed module prerequisite is too low', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 5',
                },
                courses: {
                    completed: [
                        { code: 'D3', grade: '2' },
                        { code: 'SPA1', grade: '2' },
                    ],
                    failed: [],
                    missing: [],
                    planned: [],
                },
            },
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    language: 'SPA',
                    semester: 5,
                },
                transferredStudentContext: null,
            },
            subjectRows: [
                {
                    id: 1,
                    semester: 5,
                    branch: 'common',
                    json_code: 'D5',
                    json_subject: 'D',
                    name: 'Deutsch 5',
                    hours_per_week: 3,
                },
                {
                    id: 2,
                    semester: 5,
                    branch: 'common',
                    json_code: 'L/F/S4',
                    json_subject: 'L/F/S',
                    name: 'Sprache 4',
                    hours_per_week: 3,
                },
            ],
        })

        const d5Course = context.storedSemesterCourseItems.find((course) => course.code === 'D5')
        const spa4Course = context.storedSemesterCourseItems.find((course) => course.code === 'SPA4')
        const spa4CardItem = TimetableV2.methods.courseSelectionCardItem.call(context, spa4Course, 'semester')

        expect(context.storedSemesterCourseItems.map((course) => course.code)).toEqual(['D5', 'SPA4'])
        expect(TimetableV2.methods.courseItemUnavailable.call(context, d5Course, 'semester')).toBe(false)
        expect(TimetableV2.methods.courseItemSelected.call(context, d5Course, 'semester')).toBe(true)
        expect(TimetableV2.methods.courseItemUnavailable.call(context, spa4Course, 'semester')).toBe(true)
        expect(TimetableV2.methods.courseItemSelected.call(context, spa4Course, 'semester')).toBe(false)
        expect(spa4CardItem.color).toBe('error')
        expect(spa4CardItem.ariaDisabled).toBe('true')
        expect(spa4CardItem.unavailableReason).toBe('prerequisite')
        expect(spa4CardItem.title).toBe('Voraussetzung nicht erfüllt')
    })

    it('lists previous selected language modules after changing languages', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                student: {
                    semesterLabel: 'Semester 5',
                },
                courses: {
                    completed: [
                        { code: 'SPA1', grade: '2' },
                    ],
                    failed: [],
                    missing: [],
                    planned: [],
                },
            },
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    language: 'L',
                    semester: 5,
                },
                transferredStudentContext: null,
            },
            subjectRows: [
                {
                    id: 1,
                    semester: 1,
                    branch: 'common',
                    json_code: 'L/F/S1',
                    json_subject: 'L/F/S',
                    name: 'Sprache 1',
                    hours_per_week: 3,
                },
                {
                    id: 2,
                    semester: 2,
                    branch: 'common',
                    json_code: 'L/F/S2',
                    json_subject: 'L/F/S',
                    name: 'Sprache 2',
                    hours_per_week: 3,
                },
                {
                    id: 3,
                    semester: 4,
                    branch: 'common',
                    json_code: 'L/F/S3',
                    json_subject: 'L/F/S',
                    name: 'Sprache 3',
                    hours_per_week: 3,
                },
                {
                    id: 4,
                    semester: 5,
                    branch: 'common',
                    json_code: 'L/F/S4',
                    json_subject: 'L/F/S',
                    name: 'Sprache 4',
                    hours_per_week: 3,
                },
            ],
        })

        const l1Course = context.storedPlannedCourseItems.find((course) => course.code === 'L1')
        const l2Course = context.storedPlannedCourseItems.find((course) => course.code === 'L2')
        const l3Course = context.storedPlannedCourseItems.find((course) => course.code === 'L3')
        const l4Course = context.storedSemesterCourseItems.find((course) => course.code === 'L4')
        const l3CardItem = TimetableV2.methods.courseSelectionCardItem.call(context, l3Course, 'planned')
        const l4CardItem = TimetableV2.methods.courseSelectionCardItem.call(context, l4Course, 'semester')

        expect(context.storedPlannedCourseItems.map((course) => course.code)).toEqual(['L1', 'L2', 'L3'])
        expect(context.storedSemesterCourseItems.map((course) => course.code)).toEqual(['L4'])
        expect(TimetableV2.methods.courseItemUnavailable.call(context, l1Course, 'planned')).toBe(false)
        expect(TimetableV2.methods.courseItemUnavailable.call(context, l2Course, 'planned')).toBe(false)
        expect(TimetableV2.methods.courseItemUnavailable.call(context, l3Course, 'planned')).toBe(true)
        expect(TimetableV2.methods.courseItemSelected.call(context, l3Course, 'planned')).toBe(false)
        expect(TimetableV2.methods.courseItemUnavailable.call(context, l4Course, 'semester')).toBe(true)
        expect(TimetableV2.methods.courseItemSelected.call(context, l4Course, 'semester')).toBe(false)
        expect(l3CardItem.color).toBe('error')
        expect(l3CardItem.ariaDisabled).toBe('true')
        expect(l3CardItem.unavailableReason).toBe('prerequisite')
        expect(l3CardItem.title).toBe('Voraussetzung nicht erfüllt')
        expect(l4CardItem.color).toBe('error')
        expect(l4CardItem.ariaDisabled).toBe('true')
        expect(l4CardItem.unavailableReason).toBe('prerequisite')
        expect(l4CardItem.title).toBe('Voraussetzung nicht erfüllt')
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

    it('preselects one semester alternative and disables the remaining alternatives', () => {
        const storedSemesterCourseItems = Array.from({ length: 11 }, (_, index) => ({
            code: `TEST${index + 1}`,
            hours: 3,
            key: `TEST${index + 1}`,
            label: `TEST${index + 1}`,
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

        expect(selectedLimitCourseItems).toHaveLength(1)
        expect(selectedLimitCourseHours).toBe(3)
        expect(disabledSemesterCourses).toHaveLength(0)
        expect(Object.values(courseSelections).filter(Boolean)).toHaveLength(0)
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

    it('shows the selection summary and actions together in the course selection footer', () => {
        const source = readTimetableV2Source()
        const restartCardStart = source.indexOf('<v-col v-if="restartCardVisible"')
        const restartCardActionsSource = source.slice(
            restartCardStart,
            source.indexOf('</v-col>', restartCardStart),
        )

        expect(restartCardActionsSource).toContain('class="students-timetable-v2-restart-card__summary"')
        expect(restartCardActionsSource).toContain('{{ selectedCourseLimitSummary.countLabel }}')
        expect(restartCardActionsSource).toContain('{{ selectedCourseLimitSummary.hoursLabel }}')
        expect(restartCardActionsSource).toContain('Maximal 10 Module / 30 Std.')
        expect(restartCardActionsSource).toContain('class="students-timetable-v2-restart-card__restart-button"')
        expect(restartCardActionsSource.indexOf('Neustart')).toBeLessThan(restartCardActionsSource.indexOf('students-timetable-v2-restart-card__summary'))
        expect(restartCardActionsSource).toMatch(/Neustart[\s\S]*v-if="courseCardsVisible && courseLimitPreselectionResetAvailable"[\s\S]*Vorauswahl zurücksetzen[\s\S]*class="students-timetable-v2-restart-card__automatic-button"[\s\S]*Weiter/u)
        expect(restartCardActionsSource).toContain(':disabled="selectedCourseLimitExceeded"')
        expect(restartCardActionsSource).not.toContain(':disabled="!courseLimitPreselectionResetAvailable"')
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
            adaptedTimetableV2Selection: {
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
            selection: {},
            timetableV2Selection: {
                semester: 5,
                courseLimitPreselectionKey: 'old',
                courseSelections: {
                    'additional:INF2': true,
                    'planned:GW1': false,
                },
                includeDistanceLearningCourses: false,
                includeKompaktunterrichtCourses: false,
                moreOfferedCourseSelections: {
                    'more:GS1::regular': true,
                },
                offeredCourseSelections: {
                    'planned:GW1::fu': false,
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
        expect(context.storedTimetableState.adaptedTimetableV2Selection.courseSelections).toEqual({
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
            adaptedTimetableV2Selection: null,
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
        const source = readTimetableV2Source()
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
        const source = readTimetableV2Source()
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
        expect(source).toMatch(/class="students-timetable-v2-restart-card__summary"[\s\S]*Ausgewählt:[\s\S]*\{\{ selectedCourseLimitSummary\.countLabel \}\}[\s\S]*\{\{ selectedCourseLimitSummary\.hoursLabel \}\}[\s\S]*Maximal 10 Module \/ 30 Std\./u)
        expect(courseSelectionCardsSource).not.toContain('students-timetable-v2-course-selection-summary')
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
        expect(TimetableV2.methods.moreCourseDisabled.call(context, plannedMoreCourse)).toBe(true)

        TimetableV2.methods.toggleMoreCourseOffers.call(context, plannedMoreCourse)

        const plannedOfferedCourse = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, plannedMoreCourse)[0]

        expect(context.selectedMoreCourseItem?.label).toBe('GS1')
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, plannedOfferedCourse, plannedMoreCourse)).toBe(false)

        TimetableV2.methods.cancelMoreCoursesCard.call(context)

        const restoredState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        expect(restoredState.timetableV2Selection.moreOfferedCourseSelections).toBeUndefined()
        expect(context.moreCoursesVisible).toBe(false)
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.moreOfferedCourseSelectionSnapshot).toBe('')
        expect(context.moreOfferedCourseSelectionSnapshotSelections).toEqual({})
    })

    it('rebases a selected timetable module under more courses after removing it from selected modules', () => {
        let context: ReturnType<typeof timetableV2Context>
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            courseGroups: [
                { class_name: 'D1-A', course: 'D1', hour: 1, title: 'D1', weekday: 1 },
                { class_name: 'GS1-A', course: 'GS1', hour: 2, title: 'GS1', weekday: 2 },
            ],
            saveStoredTimetableState,
            storedAdditionalCourseItems: [
                { code: 'GS1', hours: 4, key: 'GS1', label: 'GS1' },
            ],
            storedPlannedCourseItems: [
                { code: 'D1', hours: 3, key: 'D1', label: 'D1' },
            ],
            storedTimetableState: {
                adaptedTimetableV2Selection: {
                    courseSelections: {
                        'planned:D1': true,
                        'additional:GS1': true,
                    },
                },
                selection: {},
                timetableV2Selection: {},
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableCalculationResult: {
                selected_timetable: {
                    slots: {
                        '1-1': {
                            sourceLabel: 'D1-A',
                        },
                        '2-2': {
                            sourceLabel: 'GS1-A',
                        },
                    },
                },
            },
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })
        const selectedAdditionalCourse = context.selectedCourseItems.find((course) => course.key === 'GS1')

        expect(context.selectedCourseItems.map((course) => course.key)).toEqual(['D1', 'GS1'])
        expect(context.moreCoursesCardItems.map((course) => course.key)).not.toContain('GS1')

        TimetableV2.methods.removeSelectedCourseItem.call(context, selectedAdditionalCourse)

        expect(context.selectedCourseItems.map((course) => course.key)).toEqual(['D1'])
        expect(context.moreCoursesCardItems.map((course) => course.key)).toContain('GS1')
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
        const closedModuleCard = TimetableV2.methods.displayedMoreCourseCardItem.call(
            context,
            moreCourse,
            TimetableV2.methods.moreCoursesDisplayContext.call(context),
        )

        expect(closedModuleCard.offerCountLabel).toBe('2/2')
        expect(closedModuleCard.status).toBe('neutral')

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        const offeredCourses = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
        const openModuleCard = TimetableV2.methods.displayedMoreCourseCardItem.call(
            context,
            moreCourse,
            TimetableV2.methods.moreCoursesDisplayContext.call(context),
        )

        expect(offeredCourses).toHaveLength(2)
        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAnySelected.call(context, moreCourse)).toBe(true)
        expect(openModuleCard.offerCountLabel).toBe('2/2')
        expect(openModuleCard.status).toBe(closedModuleCard.status)
        expect(openModuleCard.color).toBe(closedModuleCard.color)

        TimetableV2.methods.deselectSelectedMoreCourseOfferedCourses.call(context)

        const savedState = context.saveStoredTimetableState.mock.calls.at(-1)[0]
        context.storedTimetableState = savedState

        expect(savedState.timetableV2Selection.moreOfferedCourseSelections).toEqual(Object.fromEntries(
            offeredCourses.map((course) => [course.selectionKey, false])
        ))
        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAllDeselected.call(context, moreCourse)).toBe(true)
        expect(TimetableV2.computed.selectedMoreCourseHasSelectedOffers.call({
            selectedMoreCourseOfferedCourseItems: offeredCourses.map(() => ({ selected: false })),
        })).toBe(false)
        expect(TimetableV2.methods.displayedMoreCourseCardItem.call(
            context,
            moreCourse,
            TimetableV2.methods.moreCoursesDisplayContext.call(context),
        ).offerCountLabel).toBe('0/2')
        expect(TimetableV2.methods.moreCourseSelectionLocked.call(context, {
            code: 'E3',
            courseGroup: 'additional',
            selectionKey: 'additional:E3',
        })).toBe(true)
    })

    it('keeps remaining preselected more course offers selected when one offer is deselected', async () => {
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'semester:D5': false,
            },
            courseGroups: [
                { class_name: 'D5-3R-SHAM', course: 'D', hour: 14, module_code: 'D5', title: 'D', weekday: 2 },
                { class_name: 'D5-5K-AUER', course: 'D', hour: 10, module_code: 'D5', title: 'D', weekday: 5 },
                { class_name: 'D5-3U-DREI', course: 'D', hour: 1, module_code: 'D5', title: 'D', weekday: 6 },
            ],
            storedSemesterCourseItems: [
                { code: 'D5', hours: 3, key: 'D5', label: 'D5' },
            ],
        })

        await TimetableV2.methods.toggleMoreCoursesCard.call(context)

        const moreCourse = context.moreCoursesCardItems.find((course) => course.label === 'D5')

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        const offeredCourses = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
        const [firstOffer, secondOffer, thirdOffer] = offeredCourses

        expect(offeredCourses).toHaveLength(3)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, firstOffer, moreCourse)).toBe(true)

        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, firstOffer, moreCourse)

        context.storedTimetableState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, firstOffer, moreCourse)).toBe(false)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, secondOffer, moreCourse)).toBe(true)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, thirdOffer, moreCourse)).toBe(true)

        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, secondOffer, moreCourse)

        const savedState = context.saveStoredTimetableState.mock.calls.at(-1)[0]
        context.storedTimetableState = savedState

        expect(savedState.timetableV2Selection.moreOfferedCourseSelections).toMatchObject({
            [firstOffer.selectionKey]: false,
            [secondOffer.selectionKey]: false,
        })
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, firstOffer, moreCourse)).toBe(false)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, secondOffer, moreCourse)).toBe(false)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, thirdOffer, moreCourse)).toBe(true)
    })

    it('reselects a deselected more course offer without deselecting other default offers', async () => {
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'semester:D5': false,
            },
            courseGroups: [
                { class_name: 'D5-3R-SHAM', course: 'D', hour: 14, module_code: 'D5', title: 'D', weekday: 2 },
                { class_name: 'D5-5K-AUER', course: 'D', hour: 10, module_code: 'D5', title: 'D', weekday: 5 },
                { class_name: 'D5-3U-DREI', course: 'D', hour: 1, module_code: 'D5', title: 'D', weekday: 6 },
            ],
            storedSemesterCourseItems: [
                { code: 'D5', hours: 3, key: 'D5', label: 'D5' },
            ],
        })

        await TimetableV2.methods.toggleMoreCoursesCard.call(context)

        const moreCourse = context.moreCoursesCardItems.find((course) => course.label === 'D5')

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        const [firstOffer, secondOffer, thirdOffer] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)

        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, firstOffer, moreCourse)
        context.storedTimetableState = context.saveStoredTimetableState.mock.calls.at(-1)[0]

        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, firstOffer, moreCourse)).toBe(false)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, secondOffer, moreCourse)).toBe(true)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, thirdOffer, moreCourse)).toBe(true)

        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, firstOffer, moreCourse)

        const savedState = context.saveStoredTimetableState.mock.calls.at(-1)[0]
        context.storedTimetableState = savedState

        expect(savedState.timetableV2Selection.moreOfferedCourseSelections).toBeUndefined()
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, firstOffer, moreCourse)).toBe(true)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, secondOffer, moreCourse)).toBe(true)
        expect(TimetableV2.methods.moreOfferedCourseSelected.call(context, thirdOffer, moreCourse)).toBe(true)
    })

    it('does not treat explicit selected more course offers as a whitelist', async () => {
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'semester:D5': false,
            },
            courseGroups: [
                { class_name: 'D5-3R-SHAM', course: 'D', hour: 14, module_code: 'D5', title: 'D', weekday: 2 },
                { class_name: 'D5-5C-AUER', course: 'D', hour: 13, module_code: 'D5', title: 'D', weekday: 4 },
                { class_name: 'D5-5K-AUER', course: 'D', hour: 10, module_code: 'D5', title: 'D', weekday: 5 },
                { class_name: 'D5-3U-DREI', course: 'D', hour: 1, module_code: 'D5', title: 'D', weekday: 6 },
            ],
            storedSemesterCourseItems: [
                { code: 'D5', hours: 3, key: 'D5', label: 'D5' },
            ],
        })

        await TimetableV2.methods.toggleMoreCoursesCard.call(context)

        const moreCourse = context.moreCoursesCardItems.find((course) => course.label === 'D5')

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)

        const [shamOffer, currentAuerOffer, selectedAuerOffer, dreiOffer] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
        context.moreCourseAvailabilityByKey = {
            [TimetableV2.methods.moreCourseOfferAvailabilityKey.call(context, moreCourse, shamOffer)]: false,
            [TimetableV2.methods.moreCourseOfferAvailabilityKey.call(context, moreCourse, currentAuerOffer)]: false,
            [TimetableV2.methods.moreCourseOfferAvailabilityKey.call(context, moreCourse, selectedAuerOffer)]: true,
            [TimetableV2.methods.moreCourseOfferAvailabilityKey.call(context, moreCourse, dreiOffer)]: true,
        }
        context.storedTimetableState = {
            selection: {},
            timetableV2Selection: {
                moreOfferedCourseSelections: {
                    [selectedAuerOffer.selectionKey]: true,
                },
            },
            transferredStudentContext: null,
        }

        expect(context.selectedMoreCourseOfferedCourseItems.map((course) => ({
            selected: course.selected,
            unavailable: course.unavailable,
        }))).toEqual([
            { selected: false, unavailable: true },
            { selected: false, unavailable: true },
            { selected: true, unavailable: false },
            { selected: true, unavailable: false },
        ])
        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('warning')
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
            'planned:D1': true,
            'planned:GW1': true,
        })
        expect(context.storedTimetableState.adaptedTimetableV2Selection.courseSelections).toEqual({
            'planned:GW1': true,
        })
        expect(context.selectedCourseItems.map((course) => course.label)).toEqual(['GWB1'])
        expect(context.moreCoursesCardItems.map((course) => course.label)).toContain('D1')
        expect(calculateTimetables).not.toHaveBeenCalled()

        TimetableV2.methods.cancelMoreCoursesCard.call(context)

        expect(context.storedTimetableState.adaptedTimetableV2Selection.courseSelections).toEqual({
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
        expect(context.storedTimetableState.adaptedTimetableV2Selection.courseSelections).toEqual({
            'semester:subject-row-m4': false,
            'semester:M4': false,
        })
        expect(TimetableV2.methods.courseSelectedBySelections.call(
            context,
            { code: 'M4', hours: 3, key: 'M4', label: 'M4' },
            'semester',
            context.storedTimetableState.adaptedTimetableV2Selection.courseSelections,
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

        expect(context.storedTimetableState.adaptedTimetableV2Selection.courseSelections).toBeUndefined()
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
        expect(context.timetableNoDistanceLearningDraftSelected).toBe(false)
        expect(context.timetableNoDistanceLearningSelected).toBe(false)
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
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'INF2')
        const [selectedOffer] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)
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
            adaptedTimetableV2Selection: {
                courseSelections: {
                    'additional:INF2': true,
                },
            },
        }))
        expect(context.calculationTimetableV2Selection).toEqual({
            courseSelections: {
                'additional:INF2': true,
            },
        })
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

        expect(context.storedTimetableState.adaptedTimetableV2Selection.courseSelections).toEqual({
            'missing:BU2': true,
        })
        expect(context.selectedMissingCourseCardItems).toEqual([])
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

    it('persists added offer choices when returning from calculation to course review', async () => {
        let context: ReturnType<typeof timetableV2Context>
        let requestPayload
        const reviewSelection = {
            courseSelections: {
                'planned:D1': true,
            },
            offeredCourseSelections: {
                'planned:D1::D1-A': false,
            },
        }

        context = timetableV2Context({
            calculateTimetables: vi.fn(() => {
                requestPayload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

                return Promise.resolve()
            }),
            courseGroups: [
                { class_name: 'D1-A', course: 'D1', hour: 1, title: 'D1', weekday: 1 },
                { class_name: 'GS1-A', course: 'GS1', hour: 2, title: 'GS1', weekday: 2 },
                { class_name: 'GS1-B', course: 'GS1', hour: 3, title: 'GS1', weekday: 3 },
            ],
            saveStoredTimetableState: vi.fn((state) => {
                context.storedTimetableState = state
            }),
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
                adaptedTimetableV2Selection: reviewSelection,
                transferredStudentContext: null,
            },
            storedTimetableStateForSaving() {
                return context.storedTimetableState
            },
            timetableV2Step: 'timetable-calculation',
            timetableCalculationVisible: true,
        })
        const moreCourse = context.moreCoursesCardItems.find((course) => course.key === 'GS1')

        await TimetableV2.methods.openMoreCoursesCard.call(context)
        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)
        const deselectedMoreOffer = context.selectedMoreCourseOfferedCourseItems
            .find((offeredCourse) => offeredCourse.groupSelectionLabel === 'GS1-B')

        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, deselectedMoreOffer, moreCourse)
        await TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(requestPayload.selected_course_keys).toEqual(['D1'])
        expect(requestPayload.selected_additional_course_keys).toEqual(['GS1'])
        expect(requestPayload.deselected_course_group_keys).toContain(deselectedMoreOffer.backendSelectionKey)
        expect(context.calculationTimetableV2Selection.courseSelections).toEqual({
            'planned:D1': true,
            'additional:GS1': true,
        })
        expect(context.storedTimetableState.adaptedTimetableV2Selection).toMatchObject({
            courseSelections: {
                'planned:D1': true,
                'additional:GS1': true,
            },
            offeredCourseSelections: {
                ...reviewSelection.offeredCourseSelections,
                [deselectedMoreOffer.selectionKey]: false,
            },
        })

        TimetableV2.methods.backToCourseReview.call(context)
        context.timetableCalculationVisible = false
        context.courseReviewVisible = true

        expect(context.calculationTimetableV2Selection).toBeNull()
        expect(context.currentOfferedCourseSelectionOverrides()).toEqual({
            ...reviewSelection.offeredCourseSelections,
            [deselectedMoreOffer.selectionKey]: false,
        })
        expect(context.selectedCourseItems.map((course) => course.key)).toEqual(['D1', 'GS1'])
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

    it('preselects ethics and Spanish from completed modules in the stored student context', () => {
        const storedTimetableStudentContext = {
            student: {
                religion: 'evang. A.B.',
                semesterLabel: 'Semester 5',
                studentCode: 'RAS',
            },
            courses: {
                completed: [
                    { code: 'ETH1', grade: '4', label: 'ETH1' },
                    { code: 'S1', grade: '4', label: 'S1' },
                ],
                failed: [],
                missing: [],
                planned: [],
            },
        }
        const context = timetableV2Context({
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    language: 'S',
                    religion: 'Rev',
                    semester: 5,
                },
                transferredStudentContext: storedTimetableStudentContext,
            },
            storedTimetableStudentContext,
            subjectRows: [
                {
                    id: 1,
                    semester: 5,
                    branch: 'common',
                    json_code: 'S5',
                    json_subject: 'L/F/S',
                    name: 'Spanisch 5',
                    hours_per_week: 3,
                },
            ],
        })

        expect(context.effectiveTimetableV2Selection).toMatchObject({
            language: 'SPA',
            religion: 'ETH',
            semester: 5,
        })
        expect(TimetableV2.methods.studentOverviewSelectionPayload.call(context)).toMatchObject({
            language: 'SPA',
            religion: 'ETH',
            semester: 5,
        })
        expect(context.effectiveTimetableV2Selection.religion).toBe('ETH')
        expect(context.effectiveTimetableV2Selection.language).toBe('SPA')
        expect(context.storedSemesterCourseItems.map((course) => course.code)).toContain('SPA5')
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
                    details: 'INF2 - 1 - 5K - WE\n2-wöchig A: 20.02.(A)',
                    dates: ['2026-02-20'],
                },
                {
                    label: 'INF3',
                    details: 'INF3 - 1 - 5K - WE\n06.03.(A)',
                    dates: ['2026-03-06'],
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

    it('keeps whole-semester date ranges for compact courses in the adopted timetable PDF payload', () => {
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

        expect(tuesdayCell.courses[0].details).toBe('M4-3U-ALT\n16.02.-11.07. (Kompakt)')
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

        expect(fridayCell.status).toBe('filled')
        expect(fridayCell.courses[0]).toMatchObject({
            label: 'D1',
            details: 'D1 - 1 - 5K - UNT',
        })
        expect(fridayCell.markers[0]).toMatchObject({
            label: 'E7',
        })
        expect(fridayCell.markers[0].title).toContain('E7 - 1 - 5K - ONE')
        expect(fridayCell.markers[0].title).toContain('06.03.')
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

    it('uses the student name in the adopted timetable save button for both label orders', () => {
        const nameFirstContext = timetableV2Context({
            adoptedTimetableStudentContextSnapshot: {
                student: {
                    label: 'FRUK Alan · 3R · Semester 5',
                    studentCode: '50112620250275',
                },
            },
        })
        const classFirstContext = timetableV2Context({
            adoptedTimetableStudentContextSnapshot: {
                student: {
                    label: '3R · FRUK Alan · Semester 5',
                    studentCode: '50112620250275',
                },
            },
        })

        expect(nameFirstContext.adoptedPublishedTimetableStudentName).toBe('FRUK Alan')
        expect(classFirstContext.adoptedPublishedTimetableStudentName).toBe('FRUK Alan')
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

    it('preselects the first student when search results are shown', () => {
        const selectStudentDraft = vi.fn()
        const students = [
            { student_code: '1001', first_name: 'Ella', last_name: 'GEHMACHER' },
            { student_code: '1002', first_name: 'Luis', last_name: 'SOLLEDER' },
        ]
        const context = {
            normalizedStudentCode: TimetableV2.methods.normalizedStudentCode,
            selectStudentDraft,
            studentSelectionDraft: {
                studentCode: null,
            },
            studentSelectionExplicitlyCleared: false,
        }

        TimetableV2.watch.filteredStudentResults.call(context, students)

        expect(selectStudentDraft).toHaveBeenCalledOnce()
        expect(selectStudentDraft).toHaveBeenCalledWith('1001')
    })

    it('keeps the drafted student when that student remains in the filtered results', () => {
        const selectStudentDraft = vi.fn()
        const students = [
            { student_code: '1001', first_name: 'Ella', last_name: 'GEHMACHER' },
            { student_code: '1002', first_name: 'Luis', last_name: 'SOLLEDER' },
        ]
        const context = {
            normalizedStudentCode: TimetableV2.methods.normalizedStudentCode,
            selectStudentDraft,
            studentSelectionDraft: {
                studentCode: '1002',
            },
            studentSelectionExplicitlyCleared: false,
        }

        TimetableV2.watch.filteredStudentResults.call(context, students)

        expect(selectStudentDraft).not.toHaveBeenCalled()
        expect(context.studentSelectionDraft.studentCode).toBe('1002')
    })

    it('keeps an explicitly cleared student draft empty when search results change', () => {
        const students = [
            { student_code: '1001', first_name: 'Ella', last_name: 'GEHMACHER' },
            { student_code: '1002', first_name: 'Luis', last_name: 'SOLLEDER' },
        ]
        const context = {
            normalizedStudentCode: TimetableV2.methods.normalizedStudentCode,
            selectStudentDraft: TimetableV2.methods.selectStudentDraft,
            studentSelectionDraft: {
                studentCode: '1002',
            },
            studentSelectionExplicitlyCleared: false,
        }

        TimetableV2.methods.selectStudentDraft.call(context, null)
        TimetableV2.watch.filteredStudentResults.call(context, students)

        expect(context.studentSelectionDraft.studentCode).toBeNull()
        expect(context.studentSelectionExplicitlyCleared).toBe(true)
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
        expect(context.timetableMaxFreeDaysSelected).toBe(false)
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
            adaptedTimetableV2Selection: {
                courseSelections: {
                    'planned:D1': true,
                    'planned:GW1': true,
                },
            },
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
        })
        expect(context.storedTimetableState.timetableV2Selection).toMatchObject({
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
                    label: '5C · ZADRA Isabella · Semester 5',
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
        const source = readTimetableV2Source()
        const context = timetableV2Context({
            initialRouteLoading: true,
        })

        expect(TimetableV2.computed.timetableV2PageLoading.call(context)).toBe(true)
        expect(source).toContain('v-if="initialRouteLoading"')
        expect(source).toContain('class="students-timetable-v2-initial-loader"')
        expect(source).toContain('Stundenplan wird geladen')
    })

    it('disables timetable v2 page actions until the page is fully loaded', () => {
        const source = readTimetableV2Source()
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
        expect(source).toContain('@apply-course-selections="applyDraftCourseSelections"')
        expect(source).not.toContain("'students-timetable-v2-page--draft-pending': courseSelectionDraftPending")
        expect(source).not.toContain('@draft-change="courseSelectionDraftPending = $event"')
        expect(source).not.toContain('@draft-selections-change="setDraftCourseSelections"')
        expect(source).toContain(':inert="timetableV2PageActionsDisabled ? \'\' : null"')
        expect(source).toContain('v-if="timetableV2PageLoading"')
        expect(source).toContain('students-timetable-v2-loading-dots')
        expect(source).toContain('.students-timetable-v2-page--actions-disabled :deep(button)')
        expect(source).toContain('.students-timetable-v2-page--actions-disabled :deep([role="button"])')
        expect(source).not.toContain('.students-timetable-v2-page--draft-pending')
        expect(source).toContain('@keyframes students-timetable-v2-loading-dots')
    })

    it('shows the course review button card while review data is loading', () => {
        const source = readTimetableV2Source()
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
        const source = readTimetableV2Source()
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

        expect(source).toContain("'students-timetable-v2-offered-courses-card__item--selected': selected && !unavailable")
        expect(source).toContain("'students-timetable-v2-offered-courses-card__item--deselected': !selected && !unavailable")
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
        expect(payload.selected_course_group_keys).toEqual(['E2|E2-1R-REIS'])
        expect(payload.deselected_course_group_keys).toEqual(['E2|E2-2A-RAI'])
    })

    it('calculates with adapted selections instead of main selections', () => {
        const context = timetableV2Context({
            courseGroups: [
                {
                    class_name: 'E6-3R-HÖF',
                    course: 'E',
                    hour: 13,
                    module_code: 'E6',
                    semester: 2,
                    weekday: 1,
                },
                {
                    class_name: 'E6-6F-KÖN',
                    course: 'E',
                    hour: 14,
                    module_code: 'E6',
                    semester: 2,
                    weekday: 2,
                },
            ],
            storedSemesterCourseItems: [
                { code: 'E6', hours: 1, key: 'E6-1', label: 'E6' },
            ],
            storedTimetableState: {
                adaptedTimetableV2Selection: {
                    courseSelections: {
                        'semester:E6-1': true,
                    },
                    semester: 2,
                },
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'semester:E6-1': false,
                    },
                    semester: 2,
                },
                transferredStudentContext: null,
            },
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })
        const selectedCourse = context.selectedCourseItems.find((course) => course.code === 'E6')
        const deselectedOffer = TimetableV2.methods.offeredCourseItemsForSelectedCourse
            .call(context, selectedCourse)
            .find((offeredCourse) => offeredCourse.groupSelectionLabel === 'E6-6F-KÖN')

        context.storedTimetableState.adaptedTimetableV2Selection.offeredCourseSelections = {
            [deselectedOffer.selectionKey]: false,
        }

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.selected_course_keys).toEqual(['E6-1'])
        expect(payload.selected_course_group_keys).toEqual(['E6|E6-3R-HÖF'])
        expect(payload.deselected_course_group_keys).toEqual(['E6|E6-6F-KÖN'])
    })

    it('renders all offers from selected modules alphabetically and marks selected offers', () => {
        const source = readTimetableV2Source()
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            courseReviewVisible: true,
            saveStoredTimetableState,
            selectedCourseItems: [
                {
                    code: 'M1',
                    label: 'M1',
                    selectionKey: 'semester:M1',
                },
                {
                    code: 'D1',
                    label: 'D1',
                    selectionKey: 'semester:D1',
                },
            ],
            offeredCourseItemsForSelectedCourse: (course) => {
                if (course.code === 'M1') {
                    return [
                        {
                            groupSelectionLabel: 'M1-Z',
                            selectionKey: 'semester:M1::z',
                        },
                    ]
                }

                return [
                    {
                        groupSelectionLabel: 'D1-B',
                        selectionKey: 'semester:D1::b',
                    },
                    {
                        groupSelectionLabel: 'D1-A',
                        selectionKey: 'semester:D1::a',
                    },
                ]
            },
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    offeredCourseSelections: {
                        'semester:D1::b': false,
                    },
                },
                transferredStudentContext: null,
            },
        })

        expect(source).toContain('<span>Ausgewählte Angebote</span>')
        expect(source).toContain('class="students-timetable-v2-card students-timetable-v2-selected-offers-card"')
        expect(source).toContain('v-for="group in selectedAdaptedOfferedCourseGroups"')
        expect(source).toContain('v-for="offer in group.offers"')
        expect(source).toContain('class="students-timetable-v2-selected-offers-card__group"')
        expect(source).toContain('class="students-timetable-v2-selected-offers-card__group-title"')
        expect(source).toContain('icon="mdi-check"')
        expect(source).toContain('icon="mdi-close"')
        expect(source).toContain('@click.stop="selectAdaptedOfferedCourseGroupOffers(group, true)"')
        expect(source).toContain('@click.stop="selectAdaptedOfferedCourseGroupOffers(group, false)"')
        expect(source).toContain('class="students-timetable-v2-selected-offers-card__row"')
        expect(source).toContain('class="students-timetable-v2-selected-offers-card__schedule"')
        expect(source).toContain('students-timetable-v2-offerchoices')
        expect(source).toMatch(/<TimetableStudentSummary[\s\S]*students-timetable-v2-offerchoices[\s\S]*Gewählte Module/u)
        expect(source).toContain('v-for="module in offerChoiceModuleItems"')
        expect(source).toContain('v-if="!adoptedTimetableVisible"')
        expect(source).toContain('size="large"')
        expect(source).toContain(':closable="selectedCourseItemsDeletable"')
        expect(source).toContain('close-icon="mdi-close"')
        expect(source).toContain('@click:close.stop="removeSelectedCourseItem(module.course)"')
        expect(source).toContain(":icon=\"offer.selected ? 'mdi-check' : 'mdi-close'\"")
        expect(source).toContain("'students-timetable-v2-selected-offers-card__item--selected': offer.selected")
        expect(source).toContain("'students-timetable-v2-selected-offers-card__item--deselected': !offer.selected")
        expect(source).toContain('@click="toggleOfferedCourseItem(offer)"')
        expect(source).toContain('@keydown.enter.prevent="toggleOfferedCourseItem(offer)"')
        expect(source).toContain('@keydown.space.prevent="toggleOfferedCourseItem(offer)"')
        expect(source).toContain(':aria-pressed="offer.selected ? \'true\' : \'false\'"')
        expect(source).not.toContain('<span>Ausgewählte Angebiot</span>')
        expect(context.selectedAdaptedOfferedCourseItems.map((offer) => offer.label)).toEqual(['D1-A', 'D1-B', 'M1-Z'])
        expect(context.selectedAdaptedOfferedCourseItems.map((offer) => offer.selected)).toEqual([true, false, true])
        expect(context.selectedAdaptedOfferedCourseGroups.map((group) => group.label)).toEqual(['D1', 'M1'])
        expect(context.selectedAdaptedOfferedCourseGroups.map((group) => group.offers.map((offer) => offer.label))).toEqual([
            ['D1-A', 'D1-B'],
            ['M1-Z'],
        ])
        expect(context.selectedAdaptedOfferedCourseItems.every((offer) => offer.courseLabel)).toBe(true)

        TimetableV2.methods.toggleOfferedCourseItem.call(
            context,
            context.selectedAdaptedOfferedCourseItems.find((offer) => offer.label === 'D1-B'),
        )

        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            adaptedTimetableV2Selection: expect.objectContaining({
                offeredCourseSelections: {
                    'semester:D1::b': true,
                },
            }),
        }))

        saveStoredTimetableState.mockClear()

        TimetableV2.methods.selectAdaptedOfferedCourseGroupOffers.call(
            context,
            context.selectedAdaptedOfferedCourseGroups.find((group) => group.label === 'D1'),
            false,
        )

        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            adaptedTimetableV2Selection: expect.objectContaining({
                offeredCourseSelections: {
                    'semester:D1::a': false,
                    'semester:D1::b': false,
                },
            }),
        }))
    })

    it('shows offer counts on calculation and only used module names on adoption', () => {
        const offeredCourseSelections = {
            'semester:D1::b': false,
            'semester:E1::a': false,
        }
        const contextOptions = {
            selectedCourseItems: [
                {
                    code: 'M1',
                    label: 'M1',
                    selectionKey: 'semester:M1',
                },
                {
                    code: 'D1',
                    label: 'D1',
                    selectionKey: 'semester:D1',
                },
                {
                    code: 'E1',
                    label: 'E1',
                    selectionKey: 'semester:E1',
                },
            ],
            offeredCourseItemsForSelectedCourse: (course) => {
                if (course.code === 'M1') {
                    return [
                        {
                            groupSelectionLabel: 'M1-Z',
                            selectionKey: 'semester:M1::z',
                        },
                    ]
                }

                if (course.code === 'E1') {
                    return [
                        {
                            groupSelectionLabel: 'E1-A',
                            selectionKey: 'semester:E1::a',
                        },
                    ]
                }

                return [
                    {
                        groupSelectionLabel: 'D1-A',
                        selectionKey: 'semester:D1::a',
                    },
                    {
                        groupSelectionLabel: 'D1-B',
                        selectionKey: 'semester:D1::b',
                    },
                ]
            },
            storedTimetableState: {
                adaptedTimetableV2Selection: {
                    offeredCourseSelections,
                },
                selection: {},
                timetableV2Selection: {
                    offeredCourseSelections,
                },
                transferredStudentContext: null,
            },
        }
        const calculationContext = timetableV2Context({
            ...contextOptions,
            timetableCalculationVisible: true,
            timetableV2Step: 'timetable-calculation',
        })
        const adoptionContext = timetableV2Context({
            ...contextOptions,
            timetableV2Step: 'timetable-adoption',
        })

        expect(calculationContext.offerChoiceModuleItems.map((module) => ({
            countLabel: module.countLabel,
            label: module.label,
        }))).toEqual([
            {
                countLabel: '1/2',
                label: 'D1',
            },
            {
                countLabel: '1/1',
                label: 'M1',
            },
        ])
        expect(adoptionContext.offerChoiceModuleItems.map((module) => module.label)).toEqual(['D1', 'M1'])
    })

    it('shows every selected module in course review when some modules have no offers', () => {
        const selectedCourseItems = Array.from({ length: 7 }, (_, index) => ({
            code: `M${index + 1}`,
            label: `M${index + 1}`,
            selectionKey: `semester:M${index + 1}`,
        }))
        const context = timetableV2Context({
            courseReviewVisible: true,
            selectedCourseItems,
            offeredCourseItemsForSelectedCourse: (course) => Number(course.code.slice(1)) <= 3
                ? [{ selectionKey: `${course.selectionKey}::offer` }]
                : [],
            timetableV2Step: 'course-review',
        })

        expect(context.selectedAdaptedOfferedCourseGroups).toHaveLength(3)
        expect(context.offerChoiceModuleItems.map((module) => module.label)).toEqual([
            'M1',
            'M2',
            'M3',
            'M4',
            'M5',
            'M6',
            'M7',
        ])
        expect(context.offerChoiceModuleItems.map((module) => module.countLabel)).toEqual([
            '1/1',
            '1/1',
            '1/1',
            'Keine Angebote',
            'Keine Angebote',
            'Keine Angebote',
            'Keine Angebote',
        ])
    })

    it('can explicitly select offers that are excluded by instruction filters', () => {
        const saveStoredTimetableState = vi.fn()
        const filteredOffer = {
            groupSelectionLabel: 'E5-3R-HÖF',
            isKompaktunterricht: true,
            selectionKey: 'semester:E5::E5-3R-HÖF',
        }
        const context = timetableV2Context({
            courseReviewVisible: true,
            saveStoredTimetableState,
            selectedCourseItems: [
                {
                    code: 'E5',
                    label: 'E5',
                    selectionKey: 'semester:E5',
                },
            ],
            offeredCourseItemsForSelectedCourse: () => [filteredOffer],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    includeKompaktunterrichtCourses: false,
                },
                transferredStudentContext: null,
            },
        })

        expect(context.selectedAdaptedOfferedCourseItems[0].selected).toBe(false)

        TimetableV2.methods.toggleOfferedCourseItem.call(context, context.selectedAdaptedOfferedCourseItems[0])

        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            adaptedTimetableV2Selection: expect.objectContaining({
                includeKompaktunterrichtCourses: false,
                offeredCourseSelections: {
                    'semester:E5::E5-3R-HÖF': true,
                },
            }),
        }))
    })

    it('uses the visible course selections when returning from selection to course review', () => {
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            saveStoredTimetableState,
            selectedCourseLimitExceeded: false,
            storedTimetableState: {
                adaptedTimetableV2Selection: {
                    courseSelections: {
                        'semester:E6-1': true,
                    },
                    offeredCourseSelections: {
                        'semester:E6-1::offer-2': false,
                    },
                    semester: 2,
                },
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'semester:E6-1': false,
                    },
                    semester: 2,
                },
                transferredStudentContext: null,
            },
        })

        TimetableV2.methods.openCourseReview.call(context)

        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            adaptedTimetableV2Selection: expect.objectContaining({
                courseSelections: {
                    'semester:E6-1': false,
                },
                offeredCourseSelections: {
                    'semester:E6-1::offer-2': false,
                },
            }),
        }))
        expect(context.timetableV2Step).toBe('course-review')
    })

    it('uses changed course selections when returning to course review', () => {
        const saveStoredTimetableState = vi.fn()
        const currentCourseSelections = {
            'semester:E6-1': true,
            'semester:E7-1': true,
            'semester:E8-1': true,
            'semester:E9-1': true,
            'semester:E10-1': true,
            'semester:E11-1': true,
            'semester:E12-1': true,
        }
        const context = timetableV2Context({
            courseSelectionOverrides: currentCourseSelections,
            saveStoredTimetableState,
            selectedCourseLimitExceeded: false,
            storedTimetableState: {
                adaptedTimetableV2Selection: {
                    courseSelections: {
                        'semester:E6-1': true,
                        'semester:E7-1': true,
                        'semester:E8-1': true,
                    },
                    semester: 2,
                },
                selection: {},
                timetableV2Selection: {
                    courseSelections: currentCourseSelections,
                    semester: 2,
                },
                transferredStudentContext: null,
            },
        })

        TimetableV2.methods.openCourseReview.call(context)

        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            adaptedTimetableV2Selection: expect.objectContaining({
                courseSelections: currentCourseSelections,
            }),
        }))
        expect(context.timetableV2Step).toBe('course-review')
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
                    [deselectedOffer.selectionKey]: false,
                },
            },
            transferredStudentContext: null,
        }

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.selected_course_keys).toEqual(['D1'])
        expect(payload.selected_additional_course_keys).toEqual(['INF2'])
        expect(payload.selected_additional_courses_required).toBe(true)
        expect(payload.selected_course_group_keys).toEqual([selectedOffer.backendSelectionKey])
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
                    title: 'INF2',
                    weekday: 1,
                },
                {
                    display_label: 'INF2 - B',
                    hour: 2,
                    key: 'inf2-b',
                    semester: 1,
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

        context.selectedMoreCourseKey = moreCourse.selectionKey

        expect(context.selectedMoreCourseOfferedCourseItems.map((course) => ({
            available: course.classes['students-timetable-v2-offered-courses-card__item--available'],
            selected: course.selected,
            selectedClass: course.classes['students-timetable-v2-offered-courses-card__item--selected'],
            unavailable: course.unavailable,
            unavailableClass: course.classes['students-timetable-v2-offered-courses-card__item--unavailable'],
        }))).toEqual([
            {
                available: true,
                selected: true,
                selectedClass: true,
                unavailable: false,
                unavailableClass: false,
            },
            {
                available: false,
                selected: false,
                selectedClass: false,
                unavailable: true,
                unavailableClass: true,
            },
        ])

        const saveCallCount = context.saveStoredTimetableState.mock.calls.length
        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, offeredCourses[1], moreCourse)

        expect(context.saveStoredTimetableState).toHaveBeenCalledTimes(saveCallCount)

        context.moreCourseAvailabilityByKey = {
            [moreCourse.selectionKey]: false,
            [offerAvailabilityKeys[0]]: true,
            [offerAvailabilityKeys[1]]: false,
        }
        const displayContext = TimetableV2.methods.moreCoursesDisplayContext.call(context)
        const displayedMoreCourse = TimetableV2.methods.displayedMoreCourseCardItem.call(context, moreCourse, displayContext)

        expect(TimetableV2.methods.moreCourseChipColor.call(context, moreCourse)).toBe('warning')
        expect(displayedMoreCourse.color).toBe('warning')
        expect(displayedMoreCourse.classes[0]).toBe('students-timetable-v2-more-courses-card__course--warning')
        expect(displayedMoreCourse.classes[1]).toBe('students-timetable-v2-more-courses-card__course--flagged')
        expect(displayedMoreCourse.status).toBe('flagged')
        expect(TimetableV2.methods.moreCourseOfferUnavailable.call(context, offeredCourses[0], moreCourse)).toBe(false)
        expect(TimetableV2.methods.moreCourseOfferDisabled.call(context, offeredCourses[0], moreCourse)).toBe(false)
        expect(TimetableV2.methods.moreCourseOfferUnavailable.call(context, offeredCourses[1], moreCourse)).toBe(true)

        context.storedTimetableState = {
            timetableV2Selection: {
                moreOfferedCourseSelections: Object.fromEntries(
                    offerSelectionKeys.map((selectionKey) => [selectionKey, false]),
                ),
            },
        }

        const deselectedDisplayContext = TimetableV2.methods.moreCoursesDisplayContext.call(context)
        const deselectedDisplayedMoreCourse = TimetableV2.methods.displayedMoreCourseCardItem.call(
            context,
            moreCourse,
            deselectedDisplayContext,
        )

        expect(deselectedDisplayedMoreCourse.color).toBe(displayedMoreCourse.color)
        expect(deselectedDisplayedMoreCourse.warningLabel).toBe(displayedMoreCourse.warningLabel)

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

    it('keeps synthetic completed more course availability candidates independent of subject row keys', () => {
        const context = timetableV2Context({
            courseGroups: [
                {
                    class_name: 'BU - 1 - 3C - PLA',
                    course: 'BU1',
                    display_label: 'BU - 1 - 3C - PLA',
                    hour: 14,
                    key: 'bu1-pla',
                    semester: 1,
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

        expect(TimetableV2.methods.timetableV2CalculationCourseKey.call(context, moreCourse)).toBe('')
        expect(payload.candidate_courses).toEqual([])
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

    it('restores at most one stored timetable option after a page refresh', () => {
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
        expect(context.timetableMaxFreeDaysSelected).toBe(false)
        expect(context.timetableMaxFreeDaysDraftSelected).toBe(false)
        expect(context.timetableNoDistanceLearningSelected).toBe(false)
        expect(context.timetableNoDistanceLearningDraftSelected).toBe(false)
        expect(context.timetableStartsFromPeriod10Selected).toBe(false)
        expect(context.timetableStartsFromPeriod10DraftSelected).toBe(false)
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
        const source = readTimetableV2Source()

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

        expect(scheduleLabel).toBe('Di 12. 18:45-19:30, Do 12.-13. 18:45-20:15')
    })

    it('shows matching A and B two-week slots as weekly schedule slots', () => {
        const context = timetableV2Context()
        const scheduleLabel = TimetableV2.methods.compactScheduleSlotsLabel.call(context, [
            {
                from: '19:30',
                hour: 13,
                recurrenceLabel: '2-wöchig A',
                until: '20:15',
                weekday: 4,
            },
            {
                from: '20:25',
                hour: 14,
                recurrenceLabel: '2-wöchig A',
                until: '21:10',
                weekday: 4,
            },
            {
                from: '20:25',
                hour: 14,
                recurrenceLabel: '2-wöchig B',
                until: '21:10',
                weekday: 4,
            },
            {
                from: '17:05',
                hour: 10,
                recurrenceLabel: '2-wöchig B',
                until: '17:50',
                weekday: 5,
            },
            {
                from: '17:50',
                hour: 11,
                recurrenceLabel: '2-wöchig A',
                until: '18:35',
                weekday: 5,
            },
            {
                from: '17:50',
                hour: 11,
                recurrenceLabel: '2-wöchig B',
                until: '18:35',
                weekday: 5,
            },
        ])

        expect(scheduleLabel).toBe('Do 13. 2-wöchig A 19:30-20:15, Do 14. 20:25-21:10, Fr 10. 2-wöchig B 17:05-17:50, Fr 11. 17:50-18:35')

        expect(TimetableV2.methods.compactScheduleSlotsLabel.call(context, [
            {
                from: '19:30',
                hour: 13,
                recurrenceLabel: '2-wöchig A',
                until: '20:15',
                weekday: 4,
            },
            {
                from: '20:25',
                hour: 14,
                recurrenceLabel: '2-wöchig A, 2-wöchig B',
                until: '21:10',
                weekday: 4,
            },
            {
                from: '17:05',
                hour: 10,
                recurrenceLabel: '2-wöchig B',
                until: '17:50',
                weekday: 5,
            },
            {
                from: '17:50',
                hour: 11,
                recurrenceLabel: '2-wöchig A, 2-wöchig B',
                until: '18:35',
                weekday: 5,
            },
        ])).toBe('Do 13. 2-wöchig A 19:30-20:15, Do 14. 20:25-21:10, Fr 10. 2-wöchig B 17:05-17:50, Fr 11. 17:50-18:35')
    })

    it('shows imported times on merged offered course schedule labels', () => {
        const selectedCourse = {
            code: 'D5',
            label: 'D5',
            selectionKey: 'semester:D5',
        }
        const context = timetableV2Context({
            courseGroups: [
                {
                    class_name: 'D5-5C-AUER',
                    course: 'D',
                    dates: ['2026-02-19', '2026-03-05'],
                    ends_at: '20:15',
                    hour: 13,
                    module_code: 'D5',
                    recurrence_interval: 2,
                    starts_at: '19:30',
                    title: 'D',
                    weekday: 4,
                },
                {
                    class_name: 'D5-5C-AUER',
                    course: 'D',
                    dates: ['2026-02-19', '2026-03-05'],
                    ends_at: '21:10',
                    hour: 14,
                    module_code: 'D5',
                    recurrence_interval: 2,
                    starts_at: '20:25',
                    title: 'D',
                    weekday: 4,
                },
                {
                    class_name: 'D5-5C-AUER',
                    course: 'D',
                    dates: ['2026-02-26', '2026-03-12'],
                    ends_at: '21:10',
                    hour: 14,
                    module_code: 'D5',
                    recurrence_interval: 2,
                    starts_at: '20:25',
                    title: 'D',
                    weekday: 4,
                },
                {
                    class_name: 'D5-5C-AUER',
                    course: 'D',
                    dates: ['2026-02-27', '2026-03-13'],
                    ends_at: '17:50',
                    hour: 10,
                    module_code: 'D5',
                    recurrence_interval: 2,
                    starts_at: '17:05',
                    title: 'D',
                    weekday: 5,
                },
                {
                    class_name: 'D5-5C-AUER',
                    course: 'D',
                    dates: ['2026-02-20', '2026-03-06'],
                    ends_at: '18:35',
                    hour: 11,
                    module_code: 'D5',
                    recurrence_interval: 2,
                    starts_at: '17:50',
                    title: 'D',
                    weekday: 5,
                },
                {
                    class_name: 'D5-5C-AUER',
                    course: 'D',
                    dates: ['2026-02-27', '2026-03-13'],
                    ends_at: '18:35',
                    hour: 11,
                    module_code: 'D5',
                    recurrence_interval: 2,
                    starts_at: '17:50',
                    title: 'D',
                    weekday: 5,
                },
            ],
        })

        const offeredCourse = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, selectedCourse)[0]

        expect(offeredCourse.scheduleLabel).toBe('Do 13. 2-wöchig A 19:30-20:15, Do 14. 20:25-21:10, Fr 10. 2-wöchig B 17:05-17:50, Fr 11. 17:50-18:35')
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

    it('includes Normalunterricht, Fernunterricht and Kompaktunterricht course variants by default', () => {
        const source = readTimetableV2Source()
        const context = timetableV2Context({
            storedTimetableState: {
                timetableV2Selection: {},
            },
        })

        expect(source).not.toContain('label="Normalunterricht"')
        expect(source).not.toContain('label="Fernunterricht"')
        expect(source).not.toContain('label="Kompaktunterricht"')
        expect(source).not.toContain('class="students-timetable-v2-selected-courses-card__filters"')
        expect(context.includeNormalunterrichtCourseVariants).toBe(true)
        expect(context.includeDistanceLearningCourseVariants).toBe(true)
        expect(context.includeKompaktunterrichtCourseVariants).toBe(true)
        expect(context.offeredCourseSelected({
            selectionKey: 'course::regular',
            distanceLearning: false,
            isKompaktunterricht: false,
        })).toBe(true)
        expect(context.offeredCourseSelected({
            selectionKey: 'course::fu',
            distanceLearning: true,
        })).toBe(true)
        expect(context.offeredCourseSelected({
            selectionKey: 'course::compact',
            isKompaktunterricht: true,
        })).toBe(true)
    })

    it('filters Normalunterricht, Fernunterricht and Kompaktunterricht course variants when unchecked', () => {
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
                    includeNormalunterrichtCourses: false,
                    includeDistanceLearningCourses: false,
                    includeKompaktunterrichtCourses: false,
                },
            },
        })

        expect(context.includeNormalunterrichtCourseVariants).toBe(false)
        expect(context.includeDistanceLearningCourseVariants).toBe(false)
        expect(context.includeKompaktunterrichtCourseVariants).toBe(false)
        expect(context.offeredCourseSelected(selectedCourse.offeredCourses[0])).toBe(false)
        expect(context.offeredCourseSelected(selectedCourse.offeredCourses[1])).toBe(false)
        expect(context.offeredCourseSelected(selectedCourse.offeredCourses[2])).toBe(false)
        expect(context.timetableV2DeselectedOfferedCourseGroupKeys()).toEqual(['regular-key', 'fu-key', 'compact-key'])
    })

    it('keeps more module offers available independent of instruction course filters', () => {
        const offeredCourses = [
            {
                selectionKey: 'additional:GW1::regular',
                backendSelectionKey: 'regular-key',
                distanceLearning: false,
                isKompaktunterricht: false,
            },
            {
                selectionKey: 'additional:GW1::fu',
                backendSelectionKey: 'fu-key',
                distanceLearning: true,
            },
            {
                selectionKey: 'additional:GW1::compact',
                backendSelectionKey: 'compact-key',
                isKompaktunterricht: true,
            },
        ]
        const saveStoredTimetableState = vi.fn()
        const context = timetableV2Context({
            offeredCourseItemsForSelectedCourse: () => offeredCourses,
            saveStoredTimetableState,
            storedAdditionalCourseItems: [
                {
                    code: 'GW1',
                    hours: 3,
                    key: 'GW1',
                    label: 'GW1',
                },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    includeNormalunterrichtCourses: false,
                    includeKompaktunterrichtCourses: false,
                },
                transferredStudentContext: null,
            },
        })
        const moreCourse = context.moreCoursesCardItems[0]

        context.selectedMoreCourseKey = moreCourse.selectionKey

        expect(context.selectedMoreCourseOfferedCourseItems.map((course) => course.selectionKey))
            .toEqual(['additional:GW1::regular', 'additional:GW1::fu', 'additional:GW1::compact'])
        expect(TimetableV2.methods.moreOfferedCourseSelectionsForSingleCourse.call(context, moreCourse))
            .toEqual({
                'additional:GW1::compact': true,
                'additional:GW1::fu': true,
                'additional:GW1::regular': true,
            })
        expect(TimetableV2.methods.moreCourseOfferDisabled.call(context, offeredCourses[0], moreCourse)).toBe(false)

        TimetableV2.methods.toggleMoreOfferedCourseItem.call(context, offeredCourses[0], moreCourse)

        expect(saveStoredTimetableState).toHaveBeenCalledOnce()
        context.storedTimetableState = saveStoredTimetableState.mock.calls[0][0]
        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAnySelected.call(context, moreCourse)).toBe(true)

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.selected_additional_course_keys).toEqual(['GW1'])
        expect(payload.deselected_course_group_keys).toEqual(['regular-key'])
    })

    it('keeps explicitly re-added more modules in the calculation when instruction filters hide their offers', () => {
        const context = timetableV2Context({
            courseGroups: [
                {
                    class_name: 'D5-5C-SHAM',
                    course: 'D',
                    hour: 14,
                    module_code: 'D5',
                    title: 'D',
                    weekday: 2,
                },
                {
                    class_name: 'D5-5K-AUER',
                    course: 'D',
                    hour: 13,
                    module_code: 'D5',
                    title: 'D',
                    weekday: 4,
                },
            ],
            storedSemesterCourseItems: [
                { code: 'D5', hours: 3, key: 'D5', label: 'D5' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'semester:D5': true,
                    },
                    includeDistanceLearningCourses: false,
                    includeNormalunterrichtCourses: false,
                },
                transferredStudentContext: null,
            },
        })
        const d5Course = context.selectedCourseItems.find((course) => course.selectionKey === 'semester:D5')
        const [selectedOffer, deselectedOffer] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, d5Course)

        context.storedTimetableState = {
            ...context.storedTimetableState,
            timetableV2Selection: {
                ...context.storedTimetableState.timetableV2Selection,
                offeredCourseSelections: {
                    [deselectedOffer.selectionKey]: false,
                },
            },
        }

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(context.selectedCourseItems.map((course) => course.selectionKey)).toContain('semester:D5')
        expect(payload.selected_course_keys).toEqual(['D5'])
        expect(payload.selected_additional_course_keys).toEqual([])
        expect(payload.deselected_course_group_keys).toEqual([deselectedOffer.backendSelectionKey])
        expect(payload.deselected_course_group_keys).not.toContain(selectedOffer.backendSelectionKey)
    })

    it('persists selected more module offers explicitly when instruction filters would otherwise hide them', async () => {
        let context: ReturnType<typeof timetableV2Context>
        const saveStoredTimetableState = vi.fn((state) => {
            context.storedTimetableState = state
        })

        context = timetableV2Context({
            calculateTimetables: vi.fn(() => Promise.resolve()),
            courseGroups: [
                {
                    class_name: 'D5-5C-SHAM',
                    course: 'D',
                    hour: 14,
                    module_code: 'D5',
                    title: 'D',
                    weekday: 2,
                },
            ],
            saveStoredTimetableState,
            storedSemesterCourseItems: [
                { code: 'D5', hours: 3, key: 'D5', label: 'D5' },
            ],
            storedTimetableState: {
                selection: {},
                timetableV2Selection: {
                    courseSelections: {
                        'semester:D5': false,
                    },
                    includeNormalunterrichtCourses: false,
                },
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
            timetableV2Step: 'timetable-calculation',
        })

        await TimetableV2.methods.openMoreCoursesCard.call(context)

        const moreCourse = context.moreCoursesCardItems.find((course) => course.selectionKey === 'semester:D5')
        const [selectedOffer] = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)

        TimetableV2.methods.toggleMoreCourseOffers.call(context, moreCourse)
        await TimetableV2.methods.applyMoreCoursesSelection.call(context)

        expect(saveStoredTimetableState).toHaveBeenCalled()
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toMatchObject({
            'semester:D5': true,
        })
        expect(context.storedTimetableState.timetableV2Selection.offeredCourseSelections).toMatchObject({
            [selectedOffer.selectionKey]: true,
        })

        const payload = TimetableV2.methods.timetableV2CalculationPayload.call(context)

        expect(payload.selected_course_keys).toEqual(['D5'])
        expect(payload.deselected_course_group_keys).toEqual([])
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

        TimetableV2.methods.updateInstructionCourseFilter.call(context, 'includeNormalunterrichtCourses', false)
        TimetableV2.methods.updateInstructionCourseFilter.call(context, 'includeDistanceLearningCourses', false)
        TimetableV2.methods.updateInstructionCourseFilter.call(context, 'includeKompaktunterrichtCourses', true)

        expect(saveStoredTimetableState).toHaveBeenNthCalledWith(1, {
            selection: {},
            timetableV2Selection: {
                includeNormalunterrichtCourses: false,
                includeKompaktunterrichtCourses: false,
            },
            transferredStudentContext: null,
        })
        expect(saveStoredTimetableState).toHaveBeenNthCalledWith(2, {
            selection: {},
            timetableV2Selection: {
                includeDistanceLearningCourses: false,
                includeKompaktunterrichtCourses: false,
            },
            transferredStudentContext: null,
        })
        expect(saveStoredTimetableState).toHaveBeenNthCalledWith(3, {
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
        const source = readTimetableV2Source()
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

        expect(source).not.toContain("'students-timetable-v2-selected-courses-card__course--offered-partial': selectedCourseItemOfferStatusVisible && offeredCourseItemsPartlySelected(course)")
        expect(source).toMatch(/\.students-timetable-v2-selected-courses-card__course--offered-partial \{[\s\S]*background: rgba\(255, 251, 235, 0\.96\) !important;[\s\S]*color: #92400e !important;/u)
        expect(source).toMatch(/\.students-timetable-v2-selected-courses-card__course--offered-partial\.students-timetable-v2-selected-courses-card__course--active \{[\s\S]*background: #c2410c !important;[\s\S]*color: #ffffff !important;/u)
        expect(source).not.toContain("'students-timetable-v2-selected-courses-card__course--offered-deselected': selectedCourseItemOfferStatusVisible && offeredCourseItemsAllDeselected(course)")
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
        })).toBe('05.03.(A)')
        expect(TimetableV2.methods.selectedTimetableV2SlotConflicts.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'])).toHaveLength(1)
        expect(TimetableV2.methods.selectedTimetableV2ConflictLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'].conflicts[0])).toBe('M1-Grp1-MAY 3-wöchig')
        expect(TimetableV2.methods.selectedTimetableV2ConflictLabel.call(context, context.timetableCalculationResult.selected_timetable.slots['6-2'].conflicts[0], context.timetableCalculationResult.selected_timetable.slots['6-2'])).toBe('M1-Grp1-MAY 3-wöchig (10.03.(B), 17.03.(A))')
        expect(context.selectedTimetableV2ConflictSummaryItems).toEqual([
            'D1-Grp1-KRO Mo 1. überschneidet sich mit M1-Grp1-MAY 3-wöchig.',
            'INF2-Grp1-FU Sa 2. überschneidet sich mit M1-Grp1-MAY 3-wöchig (10.03.(B), 17.03.(A)).',
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
            adaptedTimetableV2Selection: expect.objectContaining({
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

    it('shows all shared dates for every conflict summary', () => {
        const ch2Dates = [
            '2026-02-24',
            '2026-03-10',
            '2026-03-24',
            '2026-04-07',
            '2026-04-21',
            '2026-05-05',
            '2026-05-19',
            '2026-06-02',
            '2026-06-16',
            '2026-06-30',
        ]
        const context = timetableV2Context({
            adoptedTimetableCalculationResult: {
                selected_timetable: {
                    number: 1,
                    slots: {
                        '2-13': {
                            code: 'E5',
                            sourceLabel: 'E5-3R-HÖF',
                            courseGroup: {
                                dates: ch2Dates.slice(0, 5),
                                hour: 13,
                                weekday: 2,
                            },
                            conflicts: [
                                {
                                    code: 'CH2',
                                    sourceLabel: 'CH2-5K-PLA',
                                    courseGroup: {
                                        dates: ch2Dates,
                                        hour: 13,
                                        recurrence_interval: 2,
                                        weekday: 2,
                                    },
                                },
                            ],
                        },
                        '2-14': {
                            code: 'D5',
                            sourceLabel: 'D5-3R-SHAM',
                            courseGroup: {
                                dates: ch2Dates,
                                hour: 14,
                                weekday: 2,
                            },
                            conflicts: [
                                {
                                    code: 'CH2',
                                    sourceLabel: 'CH2-5K-PLA',
                                    courseGroup: {
                                        dates: ch2Dates,
                                        hour: 14,
                                        recurrence_interval: 2,
                                        weekday: 2,
                                    },
                                },
                            ],
                        },
                    },
                },
            },
            adoptedTimetableSelectedNumber: 1,
            timetableV2Step: 'timetable-adoption',
        })

        expect(context.selectedTimetableV2ConflictSummaryItems).toEqual([
            'E5-3R-HÖF Di 13. überschneidet sich mit CH2-5K-PLA 2-wöchig B (24.02.(B), 10.03.(B), 24.03.(B), 07.04.(B), 21.04.(B)).',
            'D5-3R-SHAM Di 14. überschneidet sich mit CH2-5K-PLA 2-wöchig B (24.02.(B), 10.03.(B), 24.03.(B), 07.04.(B), 21.04.(B), 05.05.(B), 19.05.(B), 02.06.(B), 16.06.(B), 30.06.(B)).',
        ])
    })

    it('keeps slot-specific dates when adding multi-slot courses to an adopted timetable', () => {
        const offeredCourse = {
            code: 'CH2',
            courseGroup: {
                dates: ['2026-02-24', '2026-03-10'],
                hour: 13,
                recurrence_interval: 2,
                weekday: 2,
            },
            groupSelectionLabel: 'CH2-5K-PLA',
        }
        const selectedCourse = {
            code: 'CH2',
            courseGroup: 'planned',
            label: 'CH2',
        }
        const scheduleSlot = {
            courseGroup: {
                dates: ['2026-02-17', '2026-02-24', '2026-03-03'],
                hour: 14,
                recurrence_interval: 1,
                weekday: 2,
            },
            hour: 14,
            recurrenceInterval: 1,
            weekday: 2,
        }
        const context = timetableV2Context()

        expect(TimetableV2.methods.adoptedTimetableSlotFromOfferedCourse.call(
            context,
            offeredCourse,
            selectedCourse,
            scheduleSlot,
        )).toMatchObject({
            courseGroup: {
                dates: ['2026-02-17', '2026-02-24', '2026-03-03'],
                hour: 14,
                recurrence_interval: 1,
                weekday: 2,
            },
        })
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

        const savedTimetableV2Selection = context.saveStoredTimetableState.mock.calls.at(-1)[0].adaptedTimetableV2Selection
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
            adaptedTimetableV2Selection: expect.objectContaining({
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
            adaptedTimetableV2Selection: expect.objectContaining({
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
            adaptedTimetableV2Selection: expect.objectContaining({
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
            'LPT-1CK-DREI Mo 1. überschneidet sich mit D1-1C-GOS (17.02.(A)).',
            'LPT-1CK-DREI Di 1. überschneidet sich mit M1-1C-MAY (18.02.(A)).',
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
            { key: 'LPT|LPT-1CK-DREI', label: 'LPT-1CK-DREI 18.02.(A)' },
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
            { key: 'GWB1|GWB1-1C-HÖF', label: 'GWB1-1C-HÖF 18.02.(A)' },
            { key: 'LPT|LPT-1CK-DREI', label: 'LPT-1CK-DREI 18.02.(A)' },
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
            { key: 'LPT|LPT-1CK-DREI', label: 'LPT-1CK-DREI 18.02.(A)' },
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
            { key: 'LPT|LPT-1CK-DREI', label: 'LPT-1CK-DREI 18.02.(A)' },
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
            'Di 14.: D5-3R-SHAM Termine (24.02.(B), 10.03.(B), 24.03.(B), 07.04.(B), 21.04.(B), 05.05.(B), 12.05.(A), 19.05.(B), 02.06.(B), 16.06.(B), 30.06.(B)); E5-3R-HÖF Termine (17.02.(A), 03.03.(A), 17.03.(A), 14.04.(A)) - keine gleichen Termine.',
        ])
        expect(context.selectedTimetableV2ConflictSummaryItems).toEqual(context.selectedTimetableV2ApparentOverlapSummaryItems)
        expect(context.selectedTimetableV2ConflictResolutionOptions).toEqual([])
        const displaySameSlotEntries = TimetableV2.methods.selectedTimetableV2DisplaySameSlotEntries.call(context, slot)
        expect(displaySameSlotEntries).toHaveLength(1)
        expect(TimetableV2.methods.selectedTimetableV2SlotTitle.call(context, displaySameSlotEntries[0])).toBe('E5')
        expect(TimetableV2.methods.selectedTimetableV2SlotTimePatternLabel.call(context, displaySameSlotEntries[0], { showRegularRange: true }))
            .toBe('17.02.-14.4. (Kompakt)')
        expect(TimetableV2.methods.selectedTimetableV2SlotExactDateLabels.call(context, displaySameSlotEntries[0])).toEqual([
            '17.02.(A)',
            '03.03.(A)',
            '17.03.(A)',
            '14.04.(A)',
        ])
        expect(TimetableV2.methods.selectedTimetableV2CellClasses.call(context, 2, 14)).toMatchObject({
            'students-timetable-v2-result-grid__cell--conflict': false,
            'students-timetable-v2-result-grid__cell--filled': true,
        })
    })

    it('marks date sequences in conflict summaries as light parts', () => {
        const summary = 'Di 12.: M4-3R-SCHM Termine (17.02.(A), 24.02.(B), 03.03.(A)); M5-3R-SCHM Termine (28.04.(A), 05.05.(B)) - keine gleichen Termine.'
        const parts = TimetableV2.methods.selectedTimetableV2ConflictSummaryParts.call({}, summary)

        expect(parts.map(({ text, type }) => ({ text, type }))).toEqual([
            {
                text: 'Di 12.: M4-3R-SCHM Termine (',
                type: 'text',
            },
            {
                text: '17.02.(A), 24.02.(B), 03.03.(A)',
                type: 'date',
            },
            {
                text: '); M5-3R-SCHM Termine (',
                type: 'text',
            },
            {
                text: '28.04.(A), 05.05.(B)',
                type: 'date',
            },
            {
                text: ') - keine gleichen Termine.',
                type: 'text',
            },
        ])
    })

    it('renders conflict summary date parts with light styling', () => {
        const source = readTimetableV2Source()

        expect(source).toContain('v-for="part in selectedTimetableV2ConflictSummaryParts(conflict)"')
        expect(source).toContain("'students-timetable-v2-result-conflicts__date': part.type === 'date'")
        expect(source).toMatch(/\.students-timetable-v2-result-conflicts__date \{[\s\S]*font-weight: 300;/u)
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
