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
        robotStudents: [],
        schoolHours: [],
        subjectRows: [],
        studentCompletedCoursesLoading: false,
        subjectRowsLoading: false,
        courseGroupsLoading: false,
        courseGroupsLoaded: false,
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
        adoptedTimetableSelectionSnapshot: null,
        adoptedTimetableStudentContextSnapshot: null,
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
        conflictResolutionRecommendationByKey: {},
        conflictResolutionRecommendationLoading: false,
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
        requestMoreCourseAvailability: vi.fn((courses = []) => {
            const candidateCourses = Array.isArray(courses) ? courses : [courses]
            const availability = Object.fromEntries(candidateCourses
                .map((course) => [course.selectionKey, { available: true, valid_timetable_count: 1 }])
                .filter(([selectionKey]) => selectionKey))

            return Promise.resolve({
                data: {
                    data: {
                        availability,
                    },
                },
            })
        }),
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
        adoptedTimetableNumberLabel: {
            get() {
                return TimetableV2.computed.adoptedTimetableNumberLabel.call(context)
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
        storedTimetableV2Selection: {
            get() {
                return TimetableV2.computed.storedTimetableV2Selection.call(context)
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
    it('shows a copyable student email in the selected student card, review header, and student search results', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')

        expect(source).toContain('class="students-timetable-v2-review-card__student-email"')
        expect(source).toContain('@click.stop="copyCourseReviewStudentEmail"')
        expect(source).toContain('class="students-timetable-v2-student-context__student-email"')
        expect(source).toContain('@click.stop="copyStoredTimetableStudentEmail"')
        expect(source).toMatch(/\.students-timetable-v2-student-context__student-main\s*\{\s*display: grid;/)
        expect(source).toMatch(/\.students-timetable-v2-student-context__student-email\s*\{\s*justify-self: start;/)
        expect(source).toContain('class="students-timetable-v2-student-search-results__email"')
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

        expect(source).toMatch(/v-if="timetableCalculationVisible && timetableOptionsCardVisible" class="students-timetable-v2-calculation-card__actions"[\s\S]*@click="closeTimetableOptionsCard">\s+Abbruch/u)
        expect(source).toMatch(/class="students-timetable-v2-calculation-card__heading"[\s\S]*Stundenpläne[\s\S]*v-if="timetableCalculationProgressVisible"[\s\S]*class="students-timetable-v2-calculation-card__title-progress"[\s\S]*:model-value="timetableCalculationProgressValue"[\s\S]*max="100"[\s\S]*\{\{ timetableCalculationProgressLabel \}\}/u)
        expect(source).not.toContain('v-progress-circular')
        expect(source).not.toContain('students-timetable-v2-calculation-card__availability-loader')
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__heading \{[\s\S]*display: inline-flex;[\s\S]*align-items: center;/u)
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__title-progress \{[\s\S]*width: clamp\(140px, 24vw, 220px\);/u)
        expect(source).toMatch(/v-if="timetableCalculationLoading && !timetableCalculationProgressVisible"[\s\S]*indeterminate/u)
        expect(source).toMatch(/color="success"\s+variant="flat"\s+append-icon="mdi-check"\s+:disabled="!timetableOptionsChanged \|\| timetableCalculationLoading"\s+@click="applyTimetableOptions">\s+Anwenden/u)
        expect(source).toMatch(/<v-text-field[\s\S]*:model-value="timetableCalculationNumberDraft"[\s\S]*type="number"[\s\S]*density="comfortable"[\s\S]*hide-spin-buttons[\s\S]*prefix="Nr\."[\s\S]*@keydown\.enter\.prevent="commitSelectedTimetableV2Number"[\s\S]*@blur="commitSelectedTimetableV2Number"/u)
        expect(source).toMatch(/\.students-timetable-v2-result__number-input \{[\s\S]*width: 112px;/u)
        expect(source).toMatch(/\.students-timetable-v2-result__number-input :deep\(\.v-field\) \{[\s\S]*min-height: 40px;/u)
        expect(source).toMatch(/\.students-timetable-v2-result__number-input :deep\(\.v-field__field\) \{[\s\S]*align-items: center;[\s\S]*min-height: 40px;/u)
        expect(source).toMatch(/\.students-timetable-v2-result__number-input :deep\(\.v-text-field__prefix\) \{[\s\S]*align-items: center;[\s\S]*padding-top: 0;[\s\S]*padding-bottom: 0;/u)
        expect(source).toMatch(/class="students-timetable-v2-calculation-card__success"[\s\S]*Die Stundenpläne wurden erfolgreich erstellt\.[\s\S]*class="students-timetable-v2-calculation-card__summary"[\s\S]*class="students-timetable-v2-calculation-card__timetable-selector students-timetable-v2-result__meta"[\s\S]*aria-label="Vorheriger Stundenplan"[\s\S]*<v-text-field[\s\S]*aria-label="Stundenplan Nummer"/u)
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__timetable-selector \{[\s\S]*justify-content: flex-end;[\s\S]*margin-left: auto;/u)
        expect(source).toMatch(/@media \(max-width: 640px\) \{[\s\S]*\.students-timetable-v2-calculation-card__content \{[\s\S]*padding: 10px !important;[\s\S]*\.students-timetable-v2-result-grid \{[\s\S]*grid-template-columns: minmax\(34px, 0\.58fr\) repeat\(var\(--students-timetable-v2-result-weekdays, 5\), minmax\(0, 1fr\)\);[\s\S]*overflow-x: visible;[\s\S]*\.students-timetable-v2-result-grid__details,[\s\S]*\.students-timetable-v2-result-grid__conflicts \{[\s\S]*display: none;/u)
        expect(source).toContain(':disabled="!selectedTimetableV2PreviousAvailable || timetablePendingActionConfirmationVisible"')
        expect(source).toContain(':disabled="!selectedTimetableV2NextAvailable || timetablePendingActionConfirmationVisible"')
        expect(source).toContain(':disabled="timetableCalculationLoading || timetablePendingActionConfirmationVisible"')
        expect(source).not.toContain('v-if="timetableCalculationVisible" class="students-timetable-v2-result__meta"')
        expect(source).toMatch(/class="students-timetable-v2-result__title"[\s\S]*v-if="selectedTimetableV2RestartButtonInHeaderVisible"[\s\S]*prepend-icon="mdi-restart"[\s\S]*@click="restartTimetableV2">\s+Neustart/u)
        expect(source).toMatch(/v-if="selectedTimetableV2RestartButtonInHeaderVisible \|\| adoptedTimetableVisible"[\s\S]*class="students-timetable-v2-result__meta"[\s\S]*v-if="selectedTimetableV2RestartButtonInHeaderVisible"[\s\S]*prepend-icon="mdi-arrow-left"[\s\S]*@click="adoptedTimetableVisible \? backToTimetableCalculation\(\) : backToCourseReview\(\)">\s+Zurück/u)
        expect(source).toContain('v-if="(calculationButtonCardVisible || adoptedTimetableButtonCardVisible) && !selectedTimetableV2Result"')
        expect(source).not.toContain('Gültiger Stundenplan')
        expect(source).toMatch(/class="students-timetable-v2-result-grid"[\s\S]*class="students-timetable-v2-result-conflicts"[\s\S]*\{\{ selectedTimetableV2ConflictTitle \}\}/u)
        expect(source).toContain('v-if="selectedTimetableV2ConflictResolutionActionsVisible"')
        expect(source).toMatch(/:color="option\.color"\s+variant="tonal"\s+:prepend-icon="option\.icon"\s+:class="\{ 'students-timetable-v2-result-conflicts__action--recommended': option\.recommended \}"\s+:disabled="timetablePendingActionConfirmationVisible"\s+@click="applySelectedTimetableV2ConflictResolution\(option\)"/u)
        expect(source).toMatch(/v-else-if="timetableCalculationVisible && !moreCoursesVisible" class="students-timetable-v2-calculation-card__actions"/u)
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__actions \{[\s\S]*justify-content: flex-end;[\s\S]*flex: 0 1 auto;[\s\S]*margin-left: auto;/u)
        expect(source).toMatch(/\.students-timetable-v2-calculation-card__reset-button \{[\s\S]*margin-left: 0;/u)
        expect(source).toMatch(/class="students-timetable-v2-calculation-card__primary-actions"[\s\S]*:color="moreCoursesButtonUnavailable \? 'error' : 'primary'"\s+variant="tonal"\s+prepend-icon="mdi-plus-circle-outline"\s+:disabled="timetableCalculationLoading \|\| moreCourseAvailabilityLoading \|\| moreCoursesButtonUnavailable"\s+@click="toggleMoreCoursesCard">\s+Mehr Kurse/u)
        expect(source).toMatch(/:color="timetableOptionsButtonUnavailable \? 'error' : 'info'"\s+variant="tonal"\s+prepend-icon="mdi-cog-outline"\s+:disabled="timetableCalculationLoading \|\| moreCourseAvailabilityLoading \|\| timetableOptionsButtonUnavailable"\s+:aria-expanded="timetableOptionsCardVisible \? 'true' : 'false'"\s+@click="toggleTimetableOptionsCard">\s+Optionen/u)
        expect(source).toMatch(/color="warning"\s+variant="tonal"\s+prepend-icon="mdi-restore"\s+class="students-timetable-v2-calculation-card__reset-button"\s+:disabled="timetableCalculationLoading \|\| !timetableCalculationResetAvailable"\s+@click="resetTimetableCalculationChanges">\s+Zurücksetzen/u)
        expect(source).toMatch(/prepend-icon="mdi-tune-variant"\s+:disabled="!courseLimitPreselectionResetAvailable"\s+@click="applyCourseLimitPreselection\(true\)">\s+Vorauswahl zurücksetzen/u)
        expect(source).toContain(':icon="timetableCalculationLoadingIcon"')
        expect(source).toContain('{{ timetableCalculationLoadingLabel }}')
        expect(source).toContain('students-timetable-v2-card-column students-timetable-v2-selected-courses-column')
        expect(source).toMatch(/Ausgewählte Kurse[\s\S]*v-if="adoptedTimetableVisible"[\s\S]*class="students-timetable-v2-card students-timetable-v2-more-adopted-courses-card"[\s\S]*Weitere Kurse/u)
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
        expect(source).toMatch(/\.students-timetable-v2-more-adopted-courses-card__grid \{[\s\S]*grid-template-columns: repeat\(4, minmax\(0, 1fr\)\);/u)
        expect(source).toContain('.students-timetable-v2-more-adopted-courses-card__category--disabled')
        expect(source).toMatch(/\.students-timetable-v2-more-adopted-courses-card__category-title \{[\s\S]*overflow-wrap: anywhere;[\s\S]*hyphens: auto;/u)
        expect(source).toMatch(/@media \(max-width: 640px\) \{[\s\S]*\.students-timetable-v2-more-adopted-courses-card__grid \{[\s\S]*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);[\s\S]*\.students-timetable-v2-more-adopted-courses-card__category \{[\s\S]*min-height: 64px;[\s\S]*\.students-timetable-v2-more-adopted-courses-card__category-title \{[\s\S]*font-size: 0\.82rem;/u)
        expect(source).toMatch(/\.students-timetable-v2-more-adopted-courses-card__course-list \{[\s\S]*grid-template-columns: repeat\(auto-fit, minmax\(116px, 1fr\)\);/u)
        expect(source).toMatch(/\.students-timetable-v2-more-adopted-courses-card__course \{[\s\S]*min-height: 64px;/u)
        expect(source).not.toContain('v-for="course in card.items"')
        expect(source).not.toContain('{{ card.emptyLabel }}')
        expect(source).toMatch(/Ausgewählte Kurse[\s\S]*v-if="selectedTimetableOptionItems\.length"[\s\S]*class="students-timetable-v2-card students-timetable-v2-selected-options-card"[\s\S]*Optionen/u)
        expect(source).toContain('v-for="option in selectedTimetableOptionItems"')
        expect(source).toContain(':closable="selectedTimetableOptionItemsDeletable"')
        expect(source).toContain(':disabled="selectedTimetableSummaryChipsDisabled"')
        expect(source).toContain(':close-label="`Option ${option.label} entfernen`"')
        expect(source).toContain('@click:close.stop="removeSelectedTimetableOption(option)"')
        expect(source).toContain('{{ option.label }}')
        expect(source).toContain(':closable="selectedCourseItemsDeletable"')
        expect(source).toContain(':disabled="selectedCourseItemsDisabled"')
        expect(source).toContain(":role=\"selectedCourseItemsClickable && !selectedCourseItemsDisabled ? 'button' : undefined\"")
        expect(source).toContain('<v-card-actions v-if="courseReviewVisible" class="students-timetable-v2-course-card-footer">')
        expect(source).toContain('Hier können einzelne Kurse (z.B. Fernunterricht) abgewählt werden.')
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
        expect(source.match(/Die Auswahl kann später noch verändert werden!/gu)).toHaveLength(2)
        expect(source).toMatch(/Zusätzliche Kurse[\s\S]*Die Auswahl kann später hinzugefügt werden!/u)
        expect(source).toContain('students-timetable-v2-course-card-footer')
        expect(source).toMatch(/\.students-timetable-v2-options-card__option \{[\s\S]*background: rgba\(248, 250, 252, 0\.96\);/u)
        expect(source).toMatch(/\.students-timetable-v2-options-card__option--unavailable \{[\s\S]*background: rgba\(241, 245, 249, 0\.96\);/u)
        expect(source).toMatch(/\.students-timetable-v2-options-card__option--loading \{[\s\S]*background: rgba\(248, 250, 252, 0\.98\);/u)
        expect(source).toMatch(/v-else-if="timetableCalculationVisible" class="students-timetable-v2-calculation-card__more-course-actions"[\s\S]*Abbruch[\s\S]*Anwenden/u)
        expect(source).toMatch(/size="large"\s+color="warning"\s+variant="tonal"\s+prepend-icon="mdi-close"\s+@click="cancelMoreCoursesCard">\s+Abbruch/u)
        expect(source).toMatch(/append-icon="mdi-check"\s+:disabled="!moreCoursesSelectionChanged \|\| timetableCalculationLoading"\s+@click="applyMoreCoursesSelection">\s+Anwenden/u)
        expect(source).toMatch(/v-else-if="adoptedTimetableCourseRemovalPendingVisible"[\s\S]*@click="cancelMoreCoursesCard">\s+Abbruch[\s\S]*:disabled="!moreCoursesSelectionChanged"[\s\S]*@click="applyAdoptedTimetableCourseRemoval">\s+Anwenden/u)
        expect(source).toMatch(/<span>\{\{ adoptedTimetableVisible \? 'Übernommener Stundenplan' : 'Stundenpläne' \}\}<\/span>/u)
        expect(source).toMatch(/v-else-if="adoptedTimetableVisible" class="students-timetable-v2-calculation-card__actions"[\s\S]*prepend-icon="mdi-file-pdf-box"[\s\S]*:loading="pdfExporting"[\s\S]*@click="downloadAdoptedTimetablePdf">\s+PDF/u)
        expect(source).toMatch(/v-if="adoptedTimetableSaveVisible"[\s\S]*prepend-icon="mdi-content-save-outline"[\s\S]*:loading="publishedTimetableSaving"[\s\S]*@click="saveAdoptedPublishedStudentTimetable"[\s\S]*Speichern für/u)
        expect(source).toContain('class="students-timetable-v2-published-timetable-report"')
        expect(source).toContain('{{ adoptSelectedTimetableV2ButtonLabel }}')
        expect(source).not.toContain('Stundenplan übernehmen')
        expect(source).toContain("@click=\"adoptCurrentTimetableV2Result\"")
        expect(source).toContain("TIMETABLE_V2_ROUTE_STEPS = ['selection', 'course-review', 'timetable-calculation', 'timetable-adoption']")
        expect(source).toContain('@click="adoptedTimetableVisible ? backToTimetableCalculation() : backToCourseReview()"')
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

    it('does not render checkbox controls in the more courses chips', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')

        expect(source).not.toContain('<v-checkbox-btn')
        expect(source).toMatch(/:color="moreCourseChipColor\(course\)"\s+:variant="selectedMoreCourseItem\?\.selectionKey === course\.selectionKey && !moreCourseUnavailable\(course\) \? 'flat' : 'tonal'"\s+:disabled="moreCourseDisabled\(course\)"/u)
        expect(source).toContain("'students-timetable-v2-selected-courses-card__course--active': selectedMoreCourseItem?.selectionKey === course.selectionKey")
        expect(source).not.toContain("'students-timetable-v2-selected-courses-card__course--offered-deselected': moreCourseOfferedCourseItemsAllDeselected(course)")
        expect(source).toContain("'students-timetable-v2-more-courses-card__course--unavailable': moreCourseUnavailable(course)")
        expect(source).toMatch(/@click="toggleMoreCourseOffers\(course\)"/u)
    })

    it('renders a deselect-all button for offered courses in the more courses card', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')

        expect(source).toMatch(/v-if="selectedMoreCourseOfferedCourseItems\.length"[\s\S]*title="Alle angebotenen Kurse abwählen"/u)
        expect(source).toContain('aria-label="Alle angebotenen Kurse abwählen"')
        expect(source).toContain(':disabled="courseGroupsLoading || !!courseGroupsError || moreCourseOfferedCourseItemsAllDeselected(selectedMoreCourseItem)"')
        expect(source).toMatch(/@click\.stop="deselectSelectedMoreCourseOfferedCourses"/u)
    })

    it('shows four adopted course option cards without details', () => {
        const context = timetableV2Context({
            courseSelectionOverrides: {
                'missing:BU1': false,
                'planned:GW1': false,
            },
            storedAdditionalCourseItems: [
                { code: 'GS1', hours: 4, label: 'GS1' },
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

        TimetableV2.methods.openMoreAdoptedCourseCard.call(context, context.moreAdoptedCourseCards[0])

        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Kurse: Negative')
        expect(context.activeMoreAdoptedCourseCard?.title).toBe('Negative')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).toEqual(['BU1', 'PH1'])
        expect(context.moreAdoptedCourseItems.map((course) => course.label)).toEqual(['BU1', 'PH1'])
        expect(context.requestMoreCourseAvailability).not.toHaveBeenCalled()

        TimetableV2.methods.closeMoreAdoptedCourseCard.call(context)

        expect(context.activeMoreAdoptedCourseCard).toBeNull()
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Kurse')
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

        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Kurse: Weitere')
        expect(context.moreAdoptedCourseCandidateItems.map((course) => course.label)).toEqual([
            'CH1',
            'CH2',
            'INF2',
            'INF3',
            'L1',
            'L2',
            'M1',
            'M2',
            'OEKO1',
            'OEKO2',
            'OEKO3',
            'PH',
            'PH2',
            'S1',
            'S2',
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
            { label: 'CH', meta: '2 Kurse' },
            { label: 'INF', meta: '2 Kurse' },
            { label: 'L', meta: '2 Kurse' },
            { label: 'M', meta: '2 Kurse' },
            { label: 'OEKO', meta: '3 Kurse' },
            { label: 'PH', meta: '2 Kurse' },
            { label: 'S', meta: '2 Kurse' },
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
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Kurse: Weitere: INF')
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
            meta: '2 Kurse',
        })

        TimetableV2.methods.openMoreAdoptedCourseItem.call(context, topLevelCourse)

        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Kurse: Weitere: INF')
        expect(context.moreAdoptedCourseItems.map((course) => course.label)).toEqual(['INF2', 'INF3'])

        TimetableV2.methods.openMoreAdoptedCourseItem.call(context, context.moreAdoptedCourseItems[0])

        expect(context.selectedMoreAdoptedCourseItem?.label).toBe('INF2')
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Kurse: Weitere: INF: INF2')
        expect(context.selectedMoreAdoptedCourseOfferedCourseItems.map((offer) => offer.name)).toEqual([
            'INF 2 - 1A - FUCH',
            'INF 2 - 1B - MAIR',
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
        expect(context.selectedMoreAdoptedCourseOfferedCourseItems.map((offer) => offer.code)).toEqual(['L 1', 'L 1'])
        expect(context.selectedMoreAdoptedCourseOfferedCourseItems.map((offer) => offer.name)).toEqual([
            'L 1 - 2A - UNT',
            'L 1 - 1RU+2F - UNT',
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
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Kurse: Negative: BU1')
        expect(context.selectedMoreAdoptedCourseOfferedCourseItems.map((offer) => offer.name)).toEqual([
            'BU 1 - 1A - FUCH',
            'BU 1 - 1B - MAIR',
        ])
        expect(context.saveStoredTimetableState).not.toHaveBeenCalled()

        TimetableV2.methods.openMoreAdoptedCourseOffers.call(context, course)

        expect(context.selectedMoreAdoptedCourseItem).toBeNull()
        expect(context.moreAdoptedCoursesTitle).toBe('Weitere Kurse: Negative')
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
        expect(context.adoptedTimetableSelectionSnapshot.courseSelections).toBeUndefined()
        expect(context.selectedMoreCourseKey).toBe('')
        expect(context.saveStoredTimetableState).not.toHaveBeenCalled()
    })

    it('shows additional courses as information only in the selection card', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
        const additionalCourseCardSource = source.match(/<span>Zus.tzliche Kurse<\/span>[\s\S]*?Keine zus.tzlichen Kurse gefunden\./u)?.[0] || ''

        expect(additionalCourseCardSource).toContain('v-for="course in storedAdditionalCourseItems"')
        expect(additionalCourseCardSource).toContain('color="info"')
        expect(additionalCourseCardSource).toContain('variant="outlined"')
        expect(additionalCourseCardSource).toContain('students-timetable-v2-completed-courses__item--static')
        expect(additionalCourseCardSource).toContain('storedAdditionalCourseSummary.countLabel')
        expect(additionalCourseCardSource).toContain('storedAdditionalCourseSummary.hoursLabel')
        expect(additionalCourseCardSource).not.toContain("setCourseGroupSelection('additional'")
        expect(additionalCourseCardSource).not.toContain("toggleCourseItem(course, 'additional')")
        expect(additionalCourseCardSource).not.toContain("courseItemSelected(course, 'additional')")
        expect(additionalCourseCardSource).not.toContain('students-timetable-v2-completed-courses__item--deselected')

        const context = timetableV2Context({
            storedAdditionalCourseItems: [
                { code: 'INF2', hours: 4, label: 'INF2' },
                { code: 'GS1', hours: 3, label: 'GS1' },
            ],
        })
        const summary = TimetableV2.computed.storedAdditionalCourseSummary.call(context)

        expect(summary.countLabel).toBe('2 Kurse')
        expect(summary.hoursLabel).toBe('7 Std.')
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

        expect(source).toContain('class="students-timetable-v2-student-context__student-religion"')
        expect(source).toContain('class="students-timetable-v2-student-context__student-email"')
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
                },
                {
                    code: 'D1',
                    grade: 'N',
                },
            ],
        })

        expect(courseHistory.completed.map((course) => ({
            code: course.code,
            label: course.label,
            meta: course.meta,
        }))).toEqual([
            {
                code: 'BU1',
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
        expect(source).toContain('students-timetable-v2-completed-courses__item--completed')
        expect(source).toMatch(/<v-chip v-if="course\.meta" size="x-small" color="success" variant="tonal">/u)
    })

    it('limits selected courses by missing and planned courses only', () => {
        const additionalCourseItems = [
            { code: 'INF2', hours: 8, label: 'INF2' },
            { code: 'GS1', hours: 8, label: 'GS1' },
            { code: 'CH1', hours: 8, label: 'CH1' },
            { code: 'GW1', hours: 8, label: 'GW1' },
        ]
        const context = timetableV2Context({
            courseSelectionOverrides: Object.fromEntries(additionalCourseItems
                .map((course) => [`additional:${course.code}`, true])),
            storedAdditionalCourseItems: additionalCourseItems,
            storedMissingCourseCardItems: [
                { code: 'D1', hours: 4, label: 'D1' },
                { code: 'BU1', hours: 3, label: 'BU1' },
            ],
            storedPlannedCourseItems: [
                { code: 'M1', hours: 3, label: 'M1' },
            ],
        })

        expect(context.selectedCourseSummary.count).toBe(7)
        expect(context.selectedCourseSummary.hours).toBe(42)
        expect(context.selectedCourseLimitSummary.count).toBe(3)
        expect(context.selectedCourseLimitSummary.hours).toBe(10)
        expect(context.selectedCourseLimitReached).toBe(false)
        expect(context.selectedCourseLimitExceeded).toBe(false)
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

    it('does not preselect later planned course modules when an earlier module is negative', () => {
        const context = timetableV2Context({
            storedTimetableStudentContext: {
                courses: {
                    failed: [
                        { code: 'M2', grade: 'N' },
                    ],
                    missing: [
                        { code: 'M2', hours: 4, label: 'M2' },
                    ],
                    planned: [
                        { code: 'D1', hours: 3, label: 'D1' },
                        { code: 'M1', hours: 4, label: 'M1' },
                        { code: 'M3', hours: 4, label: 'M3' },
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
        Object.defineProperty(context, 'storedPlannedCourseItems', {
            configurable: true,
            get() {
                return TimetableV2.computed.storedPlannedCourseItems.call(context)
            },
        })

        const plannedM1Course = context.storedPlannedCourseItems.find((course) => course.code === 'M1')
        const plannedM3Course = context.storedPlannedCourseItems.find((course) => course.code === 'M3')

        expect(context.storedPlannedCourseItems.map((course) => course.code)).toEqual(['D1', 'M1', 'M3'])
        expect(context.selectedPlannedCourseItems.map((course) => course.code)).toEqual(['D1', 'M1'])
        expect(context.courseItemSelected(plannedM1Course, 'planned')).toBe(true)
        expect(context.courseItemSelected(plannedM3Course, 'planned')).toBe(false)
        expect(context.courseSelectedBySelections(plannedM3Course, 'planned', {
            'planned:M3': true,
        })).toBe(true)
    })

    it('preselects only the earliest negative course module by default', () => {
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
        expect(context.courseItemSelected(negativeBu1Course, 'missing')).toBe(true)
        expect(context.courseItemSelected(negativeBu2Course, 'missing')).toBe(false)
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

    it('uses real course keys for negative courses in the timetable calculation payload', () => {
        const backendCourseKey = '87|2|common|BU1|BU|Biologie 1|BU1'
        const context = timetableV2Context({
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

        expect(source).toContain("'students-timetable-v2-completed-courses__item--unavailable': courseItemUnavailable(course, 'planned')")
        expect(source).toContain(':title="courseItemSelectionDisabledLabel(course, \'planned\')"')
        expect(context.courseItemUnavailable(availableCourse, 'planned')).toBe(false)
        expect(context.courseItemUnavailable(unavailableCourse, 'planned')).toBe(true)
        expect(context.courseItemSelected(availableCourse, 'planned')).toBe(true)
        expect(context.courseItemSelected(unavailableCourse, 'planned')).toBe(false)
        expect(context.selectedPlannedCourseItems.map((course) => course.code)).toEqual(['D1'])
        expect(context.courseItemSelectionDisabled(unavailableCourse, 'planned')).toBe(true)
        expect(context.courseItemSelectionDisabledLabel(unavailableCourse, 'planned')).toBe('Kein angebotener Kurs vorhanden')
        expect(context.courseSelectedBySelections(unavailableCourse, 'planned', {
            'planned:RIS3': true,
        })).toBe(false)

        context.toggleCourseItem(unavailableCourse, 'planned')

        expect(saveStoredTimetableState).not.toHaveBeenCalled()
    })

    it('shows the course limit info and blocks adding more missing or planned courses at the maximum', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8')
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
            },
            saveStoredTimetableState,
            storedMissingCourseCardItems: selectedCourseItems,
            storedPlannedCourseItems: [extraCourse],
        })

        expect(source).toContain('v-if="courseCardsVisible && selectedCourseLimitReached"')
        expect(source).toMatch(/Zusätzliche Kurse[\s\S]*class="students-timetable-v2-course-selection-summary"[\s\S]*Ausgewählt[\s\S]*\{\{ selectedCourseLimitSummary\.countLabel \}\}[\s\S]*\{\{ selectedCourseLimitSummary\.hoursLabel \}\}[\s\S]*Maximal 10\/30[\s\S]*Maximum erreicht/u)
        expect(source).toContain('Maximum erreicht: Negative Kurse und Vorgesehene Kurse')
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
            courseSelectionOverrides: {
                'planned:BU1': false,
            },
            saveStoredTimetableState,
            storedMissingCourseCardItems: selectedCourseItems,
            storedPlannedCourseItems: [exactFillCourse],
        })

        expect(context.selectedCourseLimitSummary.count).toBe(9)
        expect(context.selectedCourseLimitSummary.hours).toBe(27)
        expect(TimetableV2.methods.courseItemSelectionDisabled.call(context, exactFillCourse, 'planned')).toBe(false)

        TimetableV2.methods.toggleCourseItem.call(context, exactFillCourse, 'planned')

        expect(saveStoredTimetableState).toHaveBeenCalledOnce()
        expect(saveStoredTimetableState.mock.calls[0][0].timetableV2Selection.courseSelections).toBeUndefined()
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
        const disabledAdditionalCourses = Object.entries(courseSelections)
            .filter(([selectionKey, selected]) => selectionKey.startsWith('additional:') && selected === false)
        const selectedLimitCourseHours = TimetableV2.methods.courseLimitSelectedCourseItems.call(context, courseSelections)
            .reduce((hours, courseItem) => hours + TimetableV2.methods.courseHoursNumber.call(context, courseItem.course), 0)

        expect(disabledPlannedCourses).toHaveLength(2)
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

        let savedState = context.saveStoredTimetableState.mock.calls.at(-1)[0]
        context.storedTimetableState = savedState

        const offeredCourses = TimetableV2.methods.offeredCourseItemsForSelectedCourse.call(context, moreCourse)

        expect(offeredCourses).toHaveLength(2)
        expect(savedState.timetableV2Selection.moreOfferedCourseSelections).toEqual(Object.fromEntries(
            offeredCourses.map((course) => [course.selectionKey, true])
        ))
        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAnySelected.call(context, moreCourse)).toBe(true)

        TimetableV2.methods.deselectSelectedMoreCourseOfferedCourses.call(context)

        savedState = context.saveStoredTimetableState.mock.calls.at(-1)[0]
        context.storedTimetableState = savedState

        expect(savedState.timetableV2Selection.moreOfferedCourseSelections).toBeUndefined()
        expect(TimetableV2.methods.moreCourseOfferedCourseItemsAllDeselected.call(context, moreCourse)).toBe(true)
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

        await TimetableV2.methods.loadMoreCourseAvailabilityForCourse.call(context, moreCourse, 1)

        expect(context.requestMoreCourseAvailability).toHaveBeenCalledWith([moreCourse])
        expect(context.moreCourseAvailabilityByKey).toEqual({
            [moreCourse.selectionKey]: false,
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
        const availabilitySignature = TimetableV2.methods.moreCourseAvailabilityCurrentSignature.call(context)

        await TimetableV2.methods.loadMoreCourseAvailabilityForCourse.call(context, moreCourse, 1)

        expect(availabilitySignature).toContain('"maxFreeDaysSelected":true')
        expect(context.requestMoreCourseAvailability).toHaveBeenCalledWith([moreCourse])
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
        expect(context.moreCourseAvailabilityByKey).toEqual({
            [moreCourse.selectionKey]: true,
        })
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

        await TimetableV2.methods.calculateTimetables.call(context)
        await new Promise((resolve) => {
            setTimeout(resolve, 0)
        })

        expect(requestMoreCourseAvailability).toHaveBeenCalledWith([moreCourse])
        expect(context.moreCourseAvailabilityLoading).toBe(false)
        expect(context.moreCourseAvailabilityByKey).toEqual({
            [moreCourse.selectionKey]: true,
        })
        expect(context.timetableCalculationProgressSource).toBe('availability')
        expect(context.timetableCalculationProgressValue).toBe(100)
        expect(context.timetableCalculationProgressLabel).toBe('100%')

        expect(requestMoreCourseAvailability).toHaveBeenCalledOnce()
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

    it('completes calculation progress while background more course availability checks continue', async () => {
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

            expect(context.timetableCalculationProgressCompletionPending).toBe(false)
            expect(context.timetableCalculationProgressValue).toBe(100)
            expect(context.timetableCalculationProgressLabel).toBe('100%')

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
            context.moreCoursesCardItems.map((course) => [course.selectionKey, false])
        )
        context.restorableMoreCourseAvailabilitySignatures = {}

        expect(TimetableV2.methods.moreCourseRestorable.call(context, removedMoreCourse)).toBe(false)
        expect(TimetableV2.methods.moreCourseUnavailable.call(context, removedMoreCourse)).toBe(true)
        expect(TimetableV2.methods.moreCourseUnavailable.call(context, unavailableMoreCourse)).toBe(true)
        expect(context.moreCoursesButtonUnavailable).toBe(true)

        context.moreCourseAvailabilityRequestId = 1

        await TimetableV2.methods.loadMoreCourseAvailabilityForCourse.call(context, removedMoreCourse, 1)

        expect(requestMoreCourseAvailability).toHaveBeenCalledWith([removedMoreCourse])
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
                timetableV2Selection: {},
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
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toBeUndefined()
        expect(context.adoptedTimetableSelectionSnapshot.courseSelections).toEqual({
            'planned:D1': false,
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
            'planned:D1': false,
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
                timetableV2Selection: {},
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
            'planned:D1': false,
        })
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toBeUndefined()

        TimetableV2.methods.backToTimetableCalculation.call(context)

        expect(context.timetableV2Step).toBe('timetable-calculation')
        expect(context.storedTimetableState.timetableV2Selection.courseSelections).toBeUndefined()
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

        expect(context.adoptedTimetableSelectionSnapshot.courseSelections).toBeUndefined()
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

        expect(context.storedTimetableState.timetableV2Selection.moreOfferedCourseSelections).toEqual({
            [selectedOffer.selectionKey]: true,
        })
        expect(context.storedTimetableState.timetableV2Selection.moreOfferedCourseSelections).not.toHaveProperty(deselectedOffer.selectionKey)

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

    it('does not infer the wirtschaftskundlich branch from completed INF1 courses', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['INF1']))).toBe('')
    })

    it('infers the wirtschaftskundlich branch from completed INF2 or INF3 courses', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['INF2']))).toBe('wirtschaftskundlich')
        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['INF3']))).toBe('wirtschaftskundlich')
    })

    it('infers the gymnasial branch from completed language courses', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['L1']))).toBe('gymnasial')
        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['S1']))).toBe('gymnasial')
    })

    it('infers the gymnasial branch from language courses when only INF1 is completed', () => {
        const context = timetableV2Context()

        expect(TimetableV2.methods.inferredBranchFromCourseCodes.call(context, new Set(['INF1', 'L1', 'L2']))).toBe('gymnasial')
        expect(TimetableV2.methods.timetableV2SelectionWithCourseDefaults.call(context, {}, [
            { code: 'INF1' },
            { code: 'L1' },
            { code: 'L2' },
        ])).toMatchObject({
            branch: 'gymnasial',
            language: 'L',
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
        expect(context.adoptedTimetableNumberLabel).toBe('Nr. 2 übernommen')

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
                    label: 'INF 2',
                    details: 'INF2 - 1 - 5K - WE · 2-wöchig: 20.02.',
                    dates: ['20.02.'],
                },
                {
                    label: 'INF 3',
                    details: 'INF3 - 1 - 5K - WE · 06.03.',
                    dates: ['06.03.'],
                },
            ],
        })
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
                    label: 'D 1',
                    details: 'D1 - 1 - 5K - UNT',
                },
            ],
            markers: [
                {
                    label: 'E 7',
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

    it('restores the adopted timetable result from the route after refresh', async () => {
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

    it('opens course review with all offered courses selected by default', () => {
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
        expect(saveStoredTimetableState).toHaveBeenCalledWith(expect.objectContaining({
            timetableV2Selection: {
                courseSelections: {
                    'planned:D1': true,
                    'planned:GW1': true,
                },
            },
        }))
        expect(context.storedTimetableState.timetableV2Selection.offeredCourseSelections).toBeUndefined()
        expect(context.storedTimetableState.timetableV2Selection.moreOfferedCourseSelections).toBeUndefined()
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

    it('loads robot students on mount when a restored selected student is missing email data', () => {
        const context = timetableV2Context({
            loadRobotStudents: vi.fn(() => Promise.resolve()),
            loadSubjectRows: vi.fn(() => Promise.resolve()),
            storedTimetableStudentContext: {
                student: {
                    studentCode: '200',
                    label: '5C · ZADRA Isabella · Semester 5',
                },
            },
        })

        TimetableV2.mounted.call(context)

        expect(context.loadRobotStudents).toHaveBeenCalledOnce()
        expect(context.loadStoredStudentOverview).toHaveBeenCalledWith('200')
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

        expect(source).toContain("'students-timetable-v2-selected-courses-card__course--offered-partial': offeredCourseItemsPartlySelected(course)")
        expect(source).toMatch(/\.students-timetable-v2-selected-courses-card__course--offered-partial \{[\s\S]*background: rgba\(255, 251, 235, 0\.96\)/u)
        expect(source).toContain("'students-timetable-v2-selected-courses-card__course--offered-deselected': offeredCourseItemsAllDeselected(course)")
        expect(source).toMatch(/\.students-timetable-v2-selected-courses-card__course--offered-deselected \{[\s\S]*background: rgba\(254, 226, 226, 0\.96\) !important;[\s\S]*color: #991b1b !important;/u)
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
            actionLabel: 'Kurs entfernen',
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
                timetableV2Selection: {},
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
            actionLabel: 'Kurs entfernen',
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
                    'missing:BU1': false,
                },
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
            'LPT-1CK-DREI überschneidet sich mit D1-1C-GOS (17.02.).',
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

    it('shows regular same-slot entries with overlapping date ranges as red conflicts', () => {
        const context = timetableV2Context({
            selectedCourseItems: [
                { code: 'CH2', courseGroup: 'planned', key: 'CH2', label: 'CH2', selectionKey: 'planned:CH2' },
                { code: 'M5', courseGroup: 'planned', key: 'M5', label: 'M5', selectionKey: 'planned:M5' },
            ],
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
                                        dates: ['2026-02-17', '2026-03-03', '2026-03-17', '2026-03-31', '2026-04-14', '2026-04-28', '2026-05-12', '2026-05-26', '2026-06-09', '2026-06-23', '2026-07-07'],
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
    })
})
