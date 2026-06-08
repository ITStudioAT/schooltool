import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import Overview from '@/pages/admin/studentsTimetables/overview/Overview.vue'

describe('Students timetable overview', () => {
    it('downloads a generated timetable pdf', async () => {
        const methods = (Overview as any).methods
        const originalAxios = globalThis.axios
        const post = vi.fn().mockResolvedValue({
            data: new Blob(['pdf'], { type: 'application/pdf' }),
            headers: {
                'content-disposition': 'attachment; filename="stundenplan-test.pdf"',
            },
        })
        const payload = { title: 'Stundenplan' }
        const ctx = {
            pdfExporting: false,
            timetablePdfPayload: () => payload,
            fileNameFromContentDisposition: methods.fileNameFromContentDisposition,
            downloadBlob: vi.fn(),
        }

        globalThis.axios = { post } as never

        await methods.downloadTimetablePdf.call(ctx)

        expect(post).toHaveBeenCalledWith('/api/admin/students-timetables/overview/pdf', payload, {
            responseType: 'blob',
        })
        expect(ctx.downloadBlob).toHaveBeenCalledWith(expect.any(Blob), 'stundenplan-test.pdf')
        expect(ctx.pdfExporting).toBe(false)

        globalThis.axios = originalAxios
    })

    it('shows a pending state while queued timetable updates run', () => {
        vi.useFakeTimers()
        const methods = (Overview as any).methods
        const originalRequestAnimationFrame = window.requestAnimationFrame
        let frameCallback: FrameRequestCallback | null = null

        window.requestAnimationFrame = ((callback: FrameRequestCallback): number => {
            frameCallback = callback

            return 1
        }) as typeof window.requestAnimationFrame

        const ctx = {
            timetableUpdatePending: false,
            updated: false,
            $nextTick(callback: () => void) {
                callback()
            },
        }

        methods.runTimetableUpdate.call(ctx, () => {
            ctx.updated = true
        })

        expect(ctx.timetableUpdatePending).toBe(true)
        expect(ctx.updated).toBe(false)

        frameCallback?.(0)

        expect(ctx.updated).toBe(false)
        expect(ctx.timetableUpdatePending).toBe(true)

        vi.advanceTimersByTime(0)

        expect(ctx.updated).toBe(true)
        expect(ctx.timetableUpdatePending).toBe(true)

        vi.advanceTimersByTime(1)

        expect(ctx.timetableUpdatePending).toBe(false)

        window.requestAnimationFrame = originalRequestAnimationFrame
        vi.useRealTimers()
    })

    it('builds semester course menus from main course labels', () => {
        const methods = (Overview as any).methods
        const ctx = {
            configuredCourseGroups: [
                {
                    key: 'eth-1',
                    semester: 1,
                    weekday: 1,
                    hour: 1,
                    course: 'ETH1',
                    title: 'ETH1',
                    display_label: 'ETH1 - 1RU - PLOEC',
                    subject: 'ETH',
                },
                {
                    key: 'eth-2',
                    semester: 1,
                    weekday: 2,
                    hour: 2,
                    course: 'ETH2',
                    title: 'ETH2',
                    display_label: 'ETH2 - 2RU - PLOEC',
                    subject: 'ETH',
                },
                {
                    key: 'eth-2-copy',
                    semester: 1,
                    weekday: 2,
                    hour: 2,
                    course: 'ETH2',
                    title: 'ETH2',
                    display_label: 'ETH2 - 2RU - PLOEC',
                    subject: 'ETH',
                },
                {
                    key: 'gwb',
                    semester: 1,
                    weekday: 3,
                    hour: 3,
                    course: 'GWB',
                    title: 'GWB',
                    display_label: 'GWB - 1A - HUB',
                    subject: 'GWB',
                },
                {
                    key: 'f',
                    semester: 2,
                    weekday: 4,
                    hour: 4,
                    course: 'F1',
                    title: 'F1',
                    display_label: 'F1 - 1A - ABC',
                    subject: 'F',
                },
            ],
            configuredSchoolHours: [
                { hour: 1, from: '08:00:00', until: '08:45:00' },
                { hour: 2, from: '08:50:00', until: '09:35:00' },
                { hour: 3, from: '09:45:00', until: '10:30:00' },
                { hour: 4, from: '10:40:00', until: '11:25:00' },
            ],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            displayedWeekdays: [
                { value: 1 },
                { value: 2 },
                { value: 3 },
                { value: 4 },
                { value: 5 },
            ],
            semesterCourseMenus: methods.semesterCourseMenus,
            buildSemesterCourseMenus: methods.buildSemesterCourseMenus,
            courseGroupMenuLabel: methods.courseGroupMenuLabel,
            courseMenuEntryScheduleLabelWithFrequency: methods.courseMenuEntryScheduleLabelWithFrequency,
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
            courseMenuEntryFrequencyLabel: methods.courseMenuEntryFrequencyLabel,
            courseMenuEntrySingleDateCount: methods.courseMenuEntrySingleDateCount,
            courseGroupScheduleLabel: methods.courseGroupScheduleLabel,
            weekdayForCourseGroup: methods.weekdayForCourseGroup,
            courseGroupTimeRangeLabel: methods.courseGroupTimeRangeLabel,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            formatTimeValue: methods.formatTimeValue,
            courseGroupCourseSource: methods.courseGroupCourseSource,
            mainCourseLabel: methods.mainCourseLabel,
            isTimeOnlyValue: methods.isTimeOnlyValue,
        }

        const semesterOneMenus = methods.semesterCourseMenus.call(ctx, 1)

        expect(semesterOneMenus.map((courseMenu: Record<string, string>) => courseMenu.label))
            .toEqual(['ETH', 'GWB'])
        expect(semesterOneMenus[0].entries.map((entry: Record<string, string>) => entry.label))
            .toEqual(['ETH1 - 1RU - PLOEC', 'ETH2 - 2RU - PLOEC'])
        expect(semesterOneMenus[0].entries[1].courseGroupKeys)
            .toEqual(['eth-2', 'eth-2-copy'])
        expect(semesterOneMenus[0].entries[1].scheduleLabel)
            .toBe('Di 08:50 - 09:35')
        expect(methods.semesterCourseChips.call(ctx, 2).map((chip: Record<string, string>) => chip.label))
            .toEqual(['F'])
    })

    it('adds compact frequency labels to course menu schedules', () => {
        const methods = (Overview as any).methods
        const ctx = {
            configuredSchoolHours: [
                { hour: 2, from: '08:50:00', until: '09:35:00' },
                { hour: 3, from: '09:45:00', until: '10:30:00' },
            ],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            courseMenuEntryScheduleLabelWithFrequency: methods.courseMenuEntryScheduleLabelWithFrequency,
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
            courseMenuEntryFrequencyLabel: methods.courseMenuEntryFrequencyLabel,
            courseMenuEntrySingleDateCount: methods.courseMenuEntrySingleDateCount,
            weekdayForCourseGroup: methods.weekdayForCourseGroup,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            formatTimeValue: methods.formatTimeValue,
        }

        expect(methods.courseMenuEntryScheduleLabelWithFrequency.call(ctx, {
            courseGroups: [
                {
                    weekday: 2,
                    hour: 2,
                    recurrence_interval: 2,
                    dates: ['2026-09-08', '2026-09-22'],
                },
            ],
        })).toBe('Di 08:50 - 09:35 (2w)')

        expect(methods.courseMenuEntryScheduleLabelWithFrequency.call(ctx, {
            courseGroups: [
                {
                    weekday: 3,
                    hour: 3,
                    recurrence_interval: null,
                    dates: ['2026-09-09', '2026-09-16', '2026-09-30'],
                },
            ],
        })).toBe('Mi 09:45 - 10:30 (3x)')
    })

    it('builds selected course filter chips from active menu entries', () => {
        const methods = (Overview as any).methods
        const ctx = {
            activeCourseGroupFilterKeys: ['eth-2', 'eth-2-copy'],
            configuredCourseGroups: [
                {
                    key: 'eth-2',
                    semester: 1,
                    weekday: 2,
                    hour: 2,
                    course: 'ETH2',
                    title: 'ETH2',
                    display_label: 'ETH2 - 2RU - PLOEC',
                    subject: 'ETH',
                },
                {
                    key: 'eth-2-copy',
                    semester: 1,
                    weekday: 2,
                    hour: 2,
                    course: 'ETH2',
                    title: 'ETH2',
                    display_label: 'ETH2 - 2RU - PLOEC',
                    subject: 'ETH',
                },
            ],
            configuredSchoolHours: [
                { hour: 2, from: '08:50:00', until: '09:35:00' },
            ],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            semesterCourseMenus: methods.semesterCourseMenus,
            buildSemesterCourseMenus: methods.buildSemesterCourseMenus,
            selectedCourseFilterChips: methods.selectedCourseFilterChips,
            courseGroupMenuLabel: methods.courseGroupMenuLabel,
            courseMenuEntryScheduleLabelWithFrequency: methods.courseMenuEntryScheduleLabelWithFrequency,
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
            courseMenuEntryFrequencyLabel: methods.courseMenuEntryFrequencyLabel,
            courseMenuEntrySingleDateCount: methods.courseMenuEntrySingleDateCount,
            courseGroupScheduleLabel: methods.courseGroupScheduleLabel,
            weekdayForCourseGroup: methods.weekdayForCourseGroup,
            courseGroupTimeRangeLabel: methods.courseGroupTimeRangeLabel,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            formatTimeValue: methods.formatTimeValue,
            courseGroupCourseSource: methods.courseGroupCourseSource,
            mainCourseLabel: methods.mainCourseLabel,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            isCourseMenuEntryFilterActive: methods.isCourseMenuEntryFilterActive,
            courseMenuEntryKeys: methods.courseMenuEntryKeys,
            courseMenuEntryHasOverlap: methods.courseMenuEntryHasOverlap,
            courseMenuEntryHasBlockingOverlap: methods.courseMenuEntryHasBlockingOverlap,
            courseMenuEntryHasRelatedOverlap: methods.courseMenuEntryHasRelatedOverlap,
            selectedCourseMenuEntries: methods.selectedCourseMenuEntries,
            buildSelectedCourseMenuEntries: methods.buildSelectedCourseMenuEntries,
            courseMenuEntriesHaveBlockingOverlap: methods.courseMenuEntriesHaveBlockingOverlap,
            courseMenuEntriesOverlap: methods.courseMenuEntriesOverlap,
            courseGroupsOverlap: methods.courseGroupsOverlap,
            courseGroupDates: methods.courseGroupDates,
            courseGroupDatesOverlap: methods.courseGroupDatesOverlap,
            courseGroupOverlapIsSingleDateOnly: methods.courseGroupOverlapIsSingleDateOnly,
            courseGroupIsSingleDate: methods.courseGroupIsSingleDate,
        }

        const filterChips = methods.selectedCourseFilterChips.call(ctx, 1)

        expect(filterChips.map((filterChip: Record<string, string>) => filterChip.label))
            .toEqual(['ETH2 - 2RU - PLOEC · Di 08:50 - 09:35'])

        methods.toggleCourseMenuEntryFilter.call(ctx, filterChips[0].entry)

        expect(ctx.activeCourseGroupFilterKeys).toEqual([])
    })

    it('marks selected course filter chips and timetable groups when courses overlap', () => {
        const methods = (Overview as any).methods
        const mathCourseGroup = {
            key: 'math',
            semester: 1,
            weekday: 5,
            hour: 1,
            course: 'MATH',
            title: 'MATH',
            display_label: 'MATH - 4A - KOW',
            subject: '20:25',
            teacher: '21:10',
            recurrence_interval: 1,
        }
        const mathRelatedCourseGroup = {
            key: 'math-related',
            semester: 1,
            weekday: 4,
            hour: 3,
            course: 'MATH',
            title: 'MATH',
            display_label: 'MATH - 4A - KOW',
            subject: '10:40',
            teacher: '11:25',
            recurrence_interval: 1,
        }
        const bioCourseGroup = {
            key: 'bio',
            semester: 1,
            weekday: 5,
            hour: 2,
            course: 'BIO',
            title: 'BIO',
            display_label: 'BIO - 4A - KOW',
            subject: '20:50',
            teacher: '21:30',
            recurrence_interval: 1,
        }
        const ctx = {
            activeCourseGroupFilterKeys: ['math', 'math-related', 'bio'],
            configuredCourseGroups: [mathCourseGroup, mathRelatedCourseGroup, bioCourseGroup],
            configuredSchoolHours: [],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            semesterCourseMenus: methods.semesterCourseMenus,
            buildSemesterCourseMenus: methods.buildSemesterCourseMenus,
            selectedCourseFilterChips: methods.selectedCourseFilterChips,
            selectedCourseMenuEntries: methods.selectedCourseMenuEntries,
            buildSelectedCourseMenuEntries: methods.buildSelectedCourseMenuEntries,
            courseGroupMenuLabel: methods.courseGroupMenuLabel,
            courseMenuEntryScheduleLabelWithFrequency: methods.courseMenuEntryScheduleLabelWithFrequency,
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
            courseMenuEntryFrequencyLabel: methods.courseMenuEntryFrequencyLabel,
            courseMenuEntrySingleDateCount: methods.courseMenuEntrySingleDateCount,
            courseGroupScheduleLabel: methods.courseGroupScheduleLabel,
            weekdayForCourseGroup: methods.weekdayForCourseGroup,
            courseGroupTimeRangeLabel: methods.courseGroupTimeRangeLabel,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            formatTimeValue: methods.formatTimeValue,
            courseGroupCourseSource: methods.courseGroupCourseSource,
            mainCourseLabel: methods.mainCourseLabel,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            isCourseMenuEntryFilterActive: methods.isCourseMenuEntryFilterActive,
            courseMenuEntryKeys: methods.courseMenuEntryKeys,
            courseMenuEntryHasOverlap: methods.courseMenuEntryHasOverlap,
            courseMenuEntryHasBlockingOverlap: methods.courseMenuEntryHasBlockingOverlap,
            courseMenuEntryHasRelatedOverlap: methods.courseMenuEntryHasRelatedOverlap,
            courseGroupHasOverlap: methods.courseGroupHasOverlap,
            courseGroupHasBlockingOverlap: methods.courseGroupHasBlockingOverlap,
            courseGroupHasRelatedOverlap: methods.courseGroupHasRelatedOverlap,
            displayCourseGroupsForCell: methods.displayCourseGroupsForCell,
            courseGroupsForCell: methods.courseGroupsForCell,
            courseCellKey: methods.courseCellKey,
            courseGroupMatchesSelectedRecurrenceWeek() {
                return true
            },
            courseGroupsByCell: {
                '1-5-1': [mathCourseGroup],
                '1-5-2': [bioCourseGroup],
                '1-4-3': [mathRelatedCourseGroup],
            },
            uniqueCourseGroupsByKey: methods.uniqueCourseGroupsByKey,
            uniqueDisplayCourseGroups: methods.uniqueDisplayCourseGroups,
            courseGroupDisplayIdentityKey: methods.courseGroupDisplayIdentityKey,
            courseMenuEntriesHaveBlockingOverlap: methods.courseMenuEntriesHaveBlockingOverlap,
            courseMenuEntriesOverlap: methods.courseMenuEntriesOverlap,
            courseGroupsOverlap: methods.courseGroupsOverlap,
            courseGroupDates: methods.courseGroupDates,
            courseGroupDatesOverlap: methods.courseGroupDatesOverlap,
            courseGroupOverlapIsSingleDateOnly: methods.courseGroupOverlapIsSingleDateOnly,
            courseGroupIsSingleDate: methods.courseGroupIsSingleDate,
            courseGroupSortLabel: methods.courseGroupSortLabel,
        }

        expect(methods.selectedCourseFilterChips.call(ctx, 1).map((filterChip: Record<string, boolean>) => filterChip.hasOverlap))
            .toEqual([true, true])
        expect(methods.displayCourseGroupsForCell.call(ctx, 1, 5, 1).map((courseGroup: Record<string, string>) => courseGroup.key))
            .toEqual(['bio', 'math'])
        expect(methods.displayCourseGroupsForCell.call(ctx, 1, 4, 3).map((courseGroup: Record<string, string>) => courseGroup.key))
            .toEqual(['math-related'])
        expect(methods.courseGroupHasOverlap.call(ctx, mathCourseGroup)).toBe(true)
        expect(methods.courseGroupHasRelatedOverlap.call(ctx, mathCourseGroup)).toBe(false)
        expect(methods.courseGroupHasOverlap.call(ctx, mathRelatedCourseGroup)).toBe(false)
        expect(methods.courseGroupHasRelatedOverlap.call(ctx, mathRelatedCourseGroup)).toBe(true)
        expect(methods.courseGroupHasOverlap.call(ctx, bioCourseGroup)).toBe(true)
        expect(methods.courseGroupHasRelatedOverlap.call(ctx, bioCourseGroup)).toBe(false)
    })

    it('does not render duplicate course blocks when overlapping entries share the same visible course identity', () => {
        const methods = (Overview as any).methods
        const gwCourseGroup = {
            key: 'gw1-direct',
            semester: 1,
            weekday: 1,
            hour: 11,
            course: 'GW1',
            title: 'GW1',
            display_label: 'GWB1-1C-HÖF',
            subject: '17:50',
            teacher: '18:35',
            recurrence_interval: 1,
        }
        const duplicatedGwCourseGroup = {
            ...gwCourseGroup,
            key: 'gw1-overlap-copy',
            hour: 12,
            subject: '18:30',
            teacher: '19:15',
        }
        const lptCourseGroup = {
            key: 'lpt-direct',
            semester: 1,
            weekday: 1,
            hour: 11,
            course: 'LPT',
            title: 'LPT',
            display_label: 'LPT-1CK-DREI',
            subject: '17:55',
            teacher: '18:20',
            recurrence_interval: 1,
        }
        const ctx = {
            activeCourseGroupFilterKeys: ['gw1-direct', 'gw1-overlap-copy', 'lpt-direct'],
            configuredCourseGroups: [gwCourseGroup, duplicatedGwCourseGroup, lptCourseGroup],
            configuredSchoolHours: [],
            courseGroupsByCell: {
                '1-1-11': [gwCourseGroup, lptCourseGroup],
            },
            courseCellKey: methods.courseCellKey,
            courseGroupsForCell: methods.courseGroupsForCell,
            displayCourseGroupsForCell: methods.displayCourseGroupsForCell,
            uniqueDisplayCourseGroups: methods.uniqueDisplayCourseGroups,
            courseGroupDisplayIdentityKey: methods.courseGroupDisplayIdentityKey,
            courseGroupsOverlap: methods.courseGroupsOverlap,
            courseGroupOverlapIsSingleDateOnly: methods.courseGroupOverlapIsSingleDateOnly,
            courseGroupIsSingleDate: methods.courseGroupIsSingleDate,
            courseGroupSortLabel: methods.courseGroupSortLabel,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            formatTimeValue: methods.formatTimeValue,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            courseGroupMatchesSelectedRecurrenceWeek() {
                return true
            },
        }

        expect(methods.displayCourseGroupsForCell.call(ctx, 1, 1, 11).map((courseGroup: Record<string, string>) => courseGroup.key))
            .toEqual(['gw1-direct', 'lpt-direct'])
    })

    it('shows overlapping single appointments as cell markers without marking the timetable cell red', () => {
        const methods = (Overview as any).methods
        const regularCourseGroup = {
            key: 'd1-regular',
            semester: 1,
            weekday: 5,
            hour: 7,
            course: 'D1',
            title: 'D1',
            display_label: 'D1 - 1C - GOS',
            subject: '14:45',
            teacher: '21:10',
            recurrence_interval: 1,
        }
        const singleAppointmentGroup = {
            key: 'lpt-single',
            semester: 1,
            weekday: 5,
            hour: 7,
            end_hour: 14,
            course: 'LPT',
            title: 'LPT',
            display_label: 'LPT - 1U - HER',
            subject: '14:45',
            teacher: '21:10',
            recurrence_interval: null,
            dates_count: 1,
            date: '2026-02-20',
        }
        const ctx = {
            activeCourseGroupFilterKeys: ['d1-regular', 'lpt-single'],
            configuredCourseGroups: [regularCourseGroup, singleAppointmentGroup],
            configuredSchoolHours: [],
            semesterCourseMenus: methods.semesterCourseMenus,
            buildSemesterCourseMenus: methods.buildSemesterCourseMenus,
            selectedCourseMenuEntries: methods.selectedCourseMenuEntries,
            buildSelectedCourseMenuEntries: methods.buildSelectedCourseMenuEntries,
            courseGroupMenuLabel: methods.courseGroupMenuLabel,
            courseMenuEntryScheduleLabelWithFrequency: methods.courseMenuEntryScheduleLabelWithFrequency,
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
            courseMenuEntryFrequencyLabel: methods.courseMenuEntryFrequencyLabel,
            courseMenuEntrySingleDateCount: methods.courseMenuEntrySingleDateCount,
            courseGroupScheduleLabel: methods.courseGroupScheduleLabel,
            weekdayForCourseGroup: methods.weekdayForCourseGroup,
            weekdays: [{ label: 'Fr', value: 5 }],
            courseGroupTimeRangeLabel: methods.courseGroupTimeRangeLabel,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            formatTimeValue: methods.formatTimeValue,
            courseGroupCourseSource: methods.courseGroupCourseSource,
            mainCourseLabel: methods.mainCourseLabel,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            isCourseMenuEntryFilterActive: methods.isCourseMenuEntryFilterActive,
            courseMenuEntryKeys: methods.courseMenuEntryKeys,
            courseMenuEntryHasOverlap: methods.courseMenuEntryHasOverlap,
            courseMenuEntryHasBlockingOverlap: methods.courseMenuEntryHasBlockingOverlap,
            courseMenuEntryHasRelatedOverlap: methods.courseMenuEntryHasRelatedOverlap,
            courseMenuEntriesHaveBlockingOverlap: methods.courseMenuEntriesHaveBlockingOverlap,
            courseMenuEntriesOverlap: methods.courseMenuEntriesOverlap,
            courseGroupsOverlap: methods.courseGroupsOverlap,
            courseGroupDates: methods.courseGroupDates,
            courseGroupDatesOverlap: methods.courseGroupDatesOverlap,
            courseGroupOverlapIsSingleDateOnly: methods.courseGroupOverlapIsSingleDateOnly,
            courseGroupIsSingleDate: methods.courseGroupIsSingleDate,
            courseGroupHasOverlap: methods.courseGroupHasOverlap,
            courseGroupHasBlockingOverlap: methods.courseGroupHasBlockingOverlap,
            courseGroupHasRelatedOverlap: methods.courseGroupHasRelatedOverlap,
            courseGroupHasSingleDateOverlap: methods.courseGroupHasSingleDateOverlap,
            courseGroupSingleDateOverlapMarker: methods.courseGroupSingleDateOverlapMarker,
            courseGroupSingleDateOverlapMarkersForCell: methods.courseGroupSingleDateOverlapMarkersForCell,
            courseGroupSingleDateMarkerLabel: methods.courseGroupSingleDateMarkerLabel,
            courseGroupSingleDateMarkerTitle: methods.courseGroupSingleDateMarkerTitle,
            singleDateOverviewGroupsForSemester: methods.singleDateOverviewGroupsForSemester,
            compactSingleDateOverviewCourseGroups: methods.compactSingleDateOverviewCourseGroups,
            singleDateOverviewCourseGroupSortValue: methods.singleDateOverviewCourseGroupSortValue,
            singleDateOverviewCourseGroupsCanMerge: methods.singleDateOverviewCourseGroupsCanMerge,
            singleDateOverviewMergeSignature: methods.singleDateOverviewMergeSignature,
            mergedSingleDateOverviewCourseGroup: methods.mergedSingleDateOverviewCourseGroup,
            singleDateOverviewEndHour: methods.singleDateOverviewEndHour,
            singleDateOverviewGroup: methods.singleDateOverviewGroup,
            singleDateOverviewSlotTitle: methods.singleDateOverviewSlotTitle,
            singleDateOverviewSummary: methods.singleDateOverviewSummary,
            singleDateOverviewDateLabel: methods.singleDateOverviewDateLabel,
            singleDateOverviewHourRangeLabel: methods.singleDateOverviewHourRangeLabel,
            sameSlotDateOverviewCourseTitle: methods.sameSlotDateOverviewCourseTitle,
            sameSlotDateOverviewSlotTitle: methods.sameSlotDateOverviewSlotTitle,
            sameSlotDateOverviewSlotRangeTitle: methods.sameSlotDateOverviewSlotRangeTitle,
            displayCourseGroupsForCell: methods.displayCourseGroupsForCell,
            courseGroupsForCell: methods.courseGroupsForCell,
            cellHasOverlap: methods.cellHasOverlap,
            courseCellKey: methods.courseCellKey,
            formatDateWithWeekdayLabel: methods.formatDateWithWeekdayLabel,
            weekdayLabelForDate: methods.weekdayLabelForDate,
            formatDateValue: methods.formatDateValue,
            formatCompactDateValue: methods.formatCompactDateValue,
            formatDate: methods.formatDate,
            normalizeDate: methods.normalizeDate,
            courseGroupMatchesSelectedRecurrenceWeek() {
                return true
            },
            courseGroupsByCell: {
                '1-5-7': [regularCourseGroup],
            },
            uniqueCourseGroupsByKey: methods.uniqueCourseGroupsByKey,
            uniqueDisplayCourseGroups: methods.uniqueDisplayCourseGroups,
            courseGroupDisplayIdentityKey: methods.courseGroupDisplayIdentityKey,
            courseGroupSortLabel: methods.courseGroupSortLabel,
        }

        expect(methods.displayCourseGroupsForCell.call(ctx, 1, 5, 7).map((courseGroup: Record<string, string>) => courseGroup.key))
            .toEqual(['d1-regular'])
        expect(methods.courseGroupSingleDateOverlapMarkersForCell.call(ctx, 1, 5, 7))
            .toEqual([{
                key: 'lpt-single',
                label: 'LPT',
                title: 'LPT - 1U - HER',
                courseGroup: singleAppointmentGroup,
            }])
        expect(methods.cellHasOverlap.call(ctx, 1, 5, 7)).toBe(false)
        expect(methods.courseGroupHasOverlap.call(ctx, regularCourseGroup)).toBe(false)
        expect(methods.courseGroupSingleDateOverlapMarker.call(ctx, regularCourseGroup)).toBe('Auch Einzeltermine')
        expect(methods.singleDateOverviewGroupsForSemester.call(ctx, 1)).toEqual([{
            key: 'lpt-single',
            title: 'LPT - 1U - HER',
            slotTitle: 'Fr 7.-14. 14:45 - 21:10',
            dateLabels: ['Fr, 20.02.2026'],
            summary: 'LPT - 1U - HER: Fr, 20.02., 7.-14., 14:45-21:10',
            courseGroup: singleAppointmentGroup,
            sortValue: '2026-02-20|05|07|LPT - 1U - HER',
        }])
    })

    it('compacts continued single-date lesson rows into one overview line', () => {
        const methods = (Overview as any).methods
        const lessonTimes = [
            ['14:45', '15:30'],
            ['15:30', '16:15'],
            ['16:15', '17:00'],
            ['17:05', '17:50'],
            ['17:50', '18:35'],
            ['18:45', '19:30'],
            ['19:30', '20:15'],
            ['20:25', '21:10'],
        ]
        const singleDateCourseGroups = lessonTimes.map(([subject, teacher], index) => ({
            key: `lpt-${index + 7}`,
            semester: 1,
            weekday: 5,
            hour: index + 7,
            course: 'LPT',
            title: 'LPT',
            display_label: 'LPT - 1U - HER',
            subject,
            teacher,
            recurrence_interval: null,
            date: '2026-02-20',
        }))
        const ctx = {
            activeCourseGroupFilterKeys: singleDateCourseGroups.map(courseGroup => courseGroup.key),
            configuredCourseGroups: singleDateCourseGroups,
            configuredSchoolHours: [],
            weekdays: [{ label: 'Fr', value: 5 }],
            singleDateOverviewGroupsForSemester: methods.singleDateOverviewGroupsForSemester,
            compactSingleDateOverviewCourseGroups: methods.compactSingleDateOverviewCourseGroups,
            singleDateOverviewCourseGroupSortValue: methods.singleDateOverviewCourseGroupSortValue,
            singleDateOverviewCourseGroupsCanMerge: methods.singleDateOverviewCourseGroupsCanMerge,
            singleDateOverviewMergeSignature: methods.singleDateOverviewMergeSignature,
            mergedSingleDateOverviewCourseGroup: methods.mergedSingleDateOverviewCourseGroup,
            singleDateOverviewEndHour: methods.singleDateOverviewEndHour,
            singleDateOverviewGroup: methods.singleDateOverviewGroup,
            singleDateOverviewSlotTitle: methods.singleDateOverviewSlotTitle,
            singleDateOverviewSummary: methods.singleDateOverviewSummary,
            singleDateOverviewDateLabel: methods.singleDateOverviewDateLabel,
            singleDateOverviewHourRangeLabel: methods.singleDateOverviewHourRangeLabel,
            sameSlotDateOverviewCourseTitle: methods.sameSlotDateOverviewCourseTitle,
            sameSlotDateOverviewSlotTitle: methods.sameSlotDateOverviewSlotTitle,
            sameSlotDateOverviewSlotRangeTitle: methods.sameSlotDateOverviewSlotRangeTitle,
            courseGroupDates: methods.courseGroupDates,
            courseGroupIsSingleDate: methods.courseGroupIsSingleDate,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            formatTimeValue: methods.formatTimeValue,
            formatDateWithWeekdayLabel: methods.formatDateWithWeekdayLabel,
            weekdayLabelForDate: methods.weekdayLabelForDate,
            formatDateValue: methods.formatDateValue,
            formatCompactDateValue: methods.formatCompactDateValue,
            formatDate: methods.formatDate,
            normalizeDate: methods.normalizeDate,
            uniqueCourseGroupsByKey: methods.uniqueCourseGroupsByKey,
        }

        expect(methods.singleDateOverviewGroupsForSemester.call(ctx, 1)).toEqual([{
            key: 'lpt-7|lpt-8|lpt-9|lpt-10|lpt-11|lpt-12|lpt-13|lpt-14',
            title: 'LPT - 1U - HER',
            slotTitle: 'Fr 7.-14. 14:45 - 21:10',
            dateLabels: ['Fr, 20.02.2026'],
            summary: 'LPT - 1U - HER: Fr, 20.02., 7.-14., 14:45-21:10',
            courseGroup: {
                ...singleDateCourseGroups[0],
                key: 'lpt-7|lpt-8|lpt-9|lpt-10|lpt-11|lpt-12|lpt-13|lpt-14',
                end_hour: 14,
                teacher: '21:10',
            },
            sortValue: '2026-02-20|05|07|LPT - 1U - HER',
        }])
    })

    it('marks same-cell recurring courses with separate dates as orange and lists their dates', () => {
        const methods = (Overview as any).methods
        const deutschCourseGroup = {
            key: 'd2',
            semester: 1,
            weekday: 5,
            hour: 10,
            course: 'D2',
            title: 'D2',
            display_label: 'D2 - 1U - HER',
            subject: '17:05',
            teacher: '17:50',
            recurrence_interval: 2,
            recurrence_label: '2-wöchig',
            dates: ['2026-02-21', '2026-03-07', '2026-04-18'],
        }
        const englishCourseGroup = {
            key: 'e2',
            semester: 1,
            weekday: 5,
            hour: 10,
            course: 'E2',
            title: 'E2',
            display_label: 'E2 - 1U - NIE',
            subject: '17:05',
            teacher: '17:50',
            recurrence_interval: 2,
            recurrence_label: '2-wöchig',
            dates: ['2026-05-09', '2026-05-23', '2026-07-04'],
        }
        const ctx = {
            activeCourseGroupFilterKeys: ['d2', 'e2'],
            configuredCourseGroups: [deutschCourseGroup, englishCourseGroup],
            configuredSchoolHours: [],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            courseGroupsByCell: {
                '1-5-10': [deutschCourseGroup, englishCourseGroup],
            },
            semesterCourseMenus: methods.semesterCourseMenus,
            buildSemesterCourseMenus: methods.buildSemesterCourseMenus,
            selectedCourseFilterChips: methods.selectedCourseFilterChips,
            selectedCourseMenuEntries: methods.selectedCourseMenuEntries,
            buildSelectedCourseMenuEntries: methods.buildSelectedCourseMenuEntries,
            courseGroupMenuLabel: methods.courseGroupMenuLabel,
            courseMenuEntryScheduleLabelWithFrequency: methods.courseMenuEntryScheduleLabelWithFrequency,
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
            courseMenuEntryFrequencyLabel: methods.courseMenuEntryFrequencyLabel,
            courseMenuEntrySingleDateCount: methods.courseMenuEntrySingleDateCount,
            courseGroupScheduleLabel: methods.courseGroupScheduleLabel,
            weekdayForCourseGroup: methods.weekdayForCourseGroup,
            courseGroupTimeRangeLabel: methods.courseGroupTimeRangeLabel,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            formatTimeValue: methods.formatTimeValue,
            courseGroupCourseSource: methods.courseGroupCourseSource,
            mainCourseLabel: methods.mainCourseLabel,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            isCourseMenuEntryFilterActive: methods.isCourseMenuEntryFilterActive,
            courseMenuEntryKeys: methods.courseMenuEntryKeys,
            courseMenuEntryHasOverlap: methods.courseMenuEntryHasOverlap,
            courseMenuEntryHasBlockingOverlap: methods.courseMenuEntryHasBlockingOverlap,
            courseMenuEntryHasRelatedOverlap: methods.courseMenuEntryHasRelatedOverlap,
            courseMenuEntriesHaveBlockingOverlap: methods.courseMenuEntriesHaveBlockingOverlap,
            courseMenuEntriesOverlap: methods.courseMenuEntriesOverlap,
            courseGroupHasOverlap: methods.courseGroupHasOverlap,
            courseGroupHasBlockingOverlap: methods.courseGroupHasBlockingOverlap,
            courseGroupHasRelatedOverlap: methods.courseGroupHasRelatedOverlap,
            courseGroupsOverlap: methods.courseGroupsOverlap,
            courseGroupDates: methods.courseGroupDates,
            courseGroupDatesOverlap: methods.courseGroupDatesOverlap,
            courseGroupOverlapIsSingleDateOnly: methods.courseGroupOverlapIsSingleDateOnly,
            courseGroupIsSingleDate: methods.courseGroupIsSingleDate,
            courseGroupSortLabel: methods.courseGroupSortLabel,
            courseGroupsForCell: methods.courseGroupsForCell,
            cellHasOverlap: methods.cellHasOverlap,
            cellHasRelatedOverlap: methods.cellHasRelatedOverlap,
            courseCellKey: methods.courseCellKey,
            sameSlotDateOverviewGroupsForSemester: methods.sameSlotDateOverviewGroupsForSemester,
            sameSlotDateOverviewGroup: methods.sameSlotDateOverviewGroup,
            sameSlotDateOverviewCourse: methods.sameSlotDateOverviewCourse,
            sameSlotDateOverviewCourseTitle: methods.sameSlotDateOverviewCourseTitle,
            sameSlotDateOverviewSlotTitle: methods.sameSlotDateOverviewSlotTitle,
            sameSlotDateOverviewSlotRangeTitle: methods.sameSlotDateOverviewSlotRangeTitle,
            compactSameSlotDateOverviewGroups: methods.compactSameSlotDateOverviewGroups,
            sameSlotDateOverviewGroupsCanMerge: methods.sameSlotDateOverviewGroupsCanMerge,
            mergedSameSlotDateOverviewGroup: methods.mergedSameSlotDateOverviewGroup,
            sameSlotDateOverviewCourseSignature: methods.sameSlotDateOverviewCourseSignature,
            courseGroupDateRangeLabel: methods.courseGroupDateRangeLabel,
            formatDateWithWeekdayLabel: methods.formatDateWithWeekdayLabel,
            weekdayLabelForDate: methods.weekdayLabelForDate,
            formatDateValue: methods.formatDateValue,
            formatDate: methods.formatDate,
            formatCompactDateValue: methods.formatCompactDateValue,
            normalizeDate: methods.normalizeDate,
            uniqueCourseGroupsByKey: methods.uniqueCourseGroupsByKey,
            courseGroupMatchesSelectedRecurrenceWeek() {
                return true
            },
        }

        const filterChips = methods.selectedCourseFilterChips.call(ctx, 1)
        const dateOverviewGroups = methods.sameSlotDateOverviewGroupsForSemester.call(ctx, 1)

        expect(methods.courseGroupDatesOverlap.call(ctx, deutschCourseGroup, englishCourseGroup)).toBe(false)
        expect(methods.courseGroupHasOverlap.call(ctx, deutschCourseGroup)).toBe(false)
        expect(methods.courseGroupHasRelatedOverlap.call(ctx, deutschCourseGroup)).toBe(true)
        expect(methods.cellHasOverlap.call(ctx, 1, 5, 10)).toBe(false)
        expect(methods.cellHasRelatedOverlap.call(ctx, 1, 5, 10)).toBe(true)
        expect(filterChips.map((filterChip: Record<string, boolean>) => filterChip.hasBlockingOverlap))
            .toEqual([false, false])
        expect(filterChips.map((filterChip: Record<string, boolean>) => filterChip.hasRelatedOverlap))
            .toEqual([true, true])
        expect(dateOverviewGroups).toHaveLength(1)
        expect(dateOverviewGroups[0].title).toBe('Fr 10. 17:05 - 17:50')
        expect(dateOverviewGroups[0].courses.map((course: Record<string, string>) => course.title))
            .toEqual(['D2 - 1U - HER', 'E2 - 1U - NIE'])
        expect(dateOverviewGroups[0].courses[0].dateLabels)
            .toEqual(['Sa, 21.02.2026', 'Sa, 07.03.2026', 'Sa, 18.04.2026'])

        const manualDateOverviewGroups = methods.sameSlotDateOverviewGroupsForSemester.call({
            ...ctx,
            selectedCourseMenuEntries() {
                throw new Error('Manual date overview should use active timetable course groups directly.')
            },
        }, 1)

        expect(manualDateOverviewGroups[0].courses.map((course: Record<string, string>) => course.title))
            .toEqual(['D2 - 1U - HER', 'E2 - 1U - NIE'])
    })

    it('collects consecutive manual same-slot date overview rows with identical courses', () => {
        const methods = (Overview as any).methods
        const e2Dates = ['2026-05-09', '2026-05-16', '2026-05-30']
        const m2Dates = ['2026-02-21', '2026-02-28', '2026-03-07']
        const courseGroups = [1, 2, 3].flatMap(hour => [
            {
                key: `e2-${hour}`,
                semester: 1,
                weekday: 6,
                hour,
                course: 'E2',
                title: 'E2',
                display_label: 'E2-1U-NIE',
                recurrence_interval: 1,
                dates: e2Dates,
            },
            {
                key: `m2-${hour}`,
                semester: 1,
                weekday: 6,
                hour,
                course: 'M2',
                title: 'M2',
                display_label: 'M2-2S-ALT',
                recurrence_interval: 1,
                dates: m2Dates,
            },
        ])
        const ctx = {
            activeCourseGroupFilterKeys: courseGroups.map(courseGroup => courseGroup.key),
            configuredCourseGroups: courseGroups,
            configuredSchoolHours: [
                { hour: 1, from: '09:00:00', until: '09:45:00' },
                { hour: 2, from: '09:45:00', until: '10:30:00' },
                { hour: 3, from: '10:40:00', until: '11:25:00' },
            ],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            sameSlotDateOverviewGroupsForSemester: methods.sameSlotDateOverviewGroupsForSemester,
            sameSlotDateOverviewGroup: methods.sameSlotDateOverviewGroup,
            sameSlotDateOverviewCourse: methods.sameSlotDateOverviewCourse,
            sameSlotDateOverviewCourseTitle: methods.sameSlotDateOverviewCourseTitle,
            sameSlotDateOverviewSlotTitle: methods.sameSlotDateOverviewSlotTitle,
            sameSlotDateOverviewSlotRangeTitle: methods.sameSlotDateOverviewSlotRangeTitle,
            compactSameSlotDateOverviewGroups: methods.compactSameSlotDateOverviewGroups,
            sameSlotDateOverviewGroupsCanMerge: methods.sameSlotDateOverviewGroupsCanMerge,
            mergedSameSlotDateOverviewGroup: methods.mergedSameSlotDateOverviewGroup,
            sameSlotDateOverviewCourseSignature: methods.sameSlotDateOverviewCourseSignature,
            courseGroupDateRangeLabel: methods.courseGroupDateRangeLabel,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            formatTimeValue: methods.formatTimeValue,
            formatDateWithWeekdayLabel: methods.formatDateWithWeekdayLabel,
            weekdayLabelForDate: methods.weekdayLabelForDate,
            formatDateValue: methods.formatDateValue,
            formatCompactDateValue: methods.formatCompactDateValue,
            formatDate: methods.formatDate,
            normalizeDate: methods.normalizeDate,
            uniqueCourseGroupsByKey: methods.uniqueCourseGroupsByKey,
            courseCellKey: methods.courseCellKey,
            courseGroupDates: methods.courseGroupDates,
            courseGroupIsSingleDate: methods.courseGroupIsSingleDate,
        }

        const groups = methods.sameSlotDateOverviewGroupsForSemester.call(ctx, 1)

        expect(groups).toHaveLength(1)
        expect(groups[0].title).toBe('Sa 1.-3. 09:00 - 11:25')
        expect(groups[0].courses.map((course: Record<string, string>) => course.title))
            .toEqual(['E2-1U-NIE', 'M2-2S-ALT'])
    })

    it('connects menu schedule times per weekday into one time area', () => {
        const methods = (Overview as any).methods
        const ctx = {
            configuredSchoolHours: [],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            weekdayForCourseGroup: methods.weekdayForCourseGroup,
            courseGroupTimeRangeLabel: methods.courseGroupTimeRangeLabel,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            formatTimeValue: methods.formatTimeValue,
            courseGroupScheduleLabel: methods.courseGroupScheduleLabel,
        }

        expect(methods.courseMenuEntryScheduleLabel.call(ctx, {
            courseGroups: [
                {
                    weekday: 5,
                    subject: '20:25',
                    teacher: '21:10',
                },
                {
                    weekday: 5,
                    subject: '21:20',
                    teacher: '21:55',
                },
            ],
        })).toBe('Fr 20:25 - 21:55')

        expect(methods.courseMenuEntryScheduleLabel.call(ctx, {
            courseGroups: [
                {
                    weekday: 5,
                    subject: '14:45',
                    teacher: '16:15',
                },
                {
                    weekday: 5,
                    subject: '18:45',
                    teacher: '20:15',
                },
            ],
        })).toBe('Fr 14:45 - 16:15, Fr 18:45 - 20:15')
    })

    it('skips time-only values but keeps hidden weekdays in semester course chips', () => {
        const methods = (Overview as any).methods
        const ctx = {
            configuredCourseGroups: [
                {
                    semester: 1,
                    weekday: 1,
                    course: '',
                    title: '16:15',
                    display_label: '16:15',
                    subject: '17:05',
                },
                {
                    semester: 1,
                    weekday: 6,
                    course: 'F1',
                    title: 'F1',
                    display_label: 'F1 - 1A - ABC',
                    subject: 'F',
                },
                {
                    semester: 1,
                    weekday: 2,
                    course: 'GWB',
                    title: 'GWB',
                    display_label: 'GWB - 1A - DEF',
                    subject: '17:05',
                },
            ],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            configuredSchoolHours: [],
            semesterCourseMenus: methods.semesterCourseMenus,
            buildSemesterCourseMenus: methods.buildSemesterCourseMenus,
            courseGroupMenuLabel: methods.courseGroupMenuLabel,
            courseMenuEntryScheduleLabelWithFrequency: methods.courseMenuEntryScheduleLabelWithFrequency,
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
            courseMenuEntryFrequencyLabel: methods.courseMenuEntryFrequencyLabel,
            courseMenuEntrySingleDateCount: methods.courseMenuEntrySingleDateCount,
            courseGroupScheduleLabel: methods.courseGroupScheduleLabel,
            weekdayForCourseGroup: methods.weekdayForCourseGroup,
            courseGroupTimeRangeLabel: methods.courseGroupTimeRangeLabel,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            formatTimeValue: methods.formatTimeValue,
            courseGroupCourseSource: methods.courseGroupCourseSource,
            mainCourseLabel: methods.mainCourseLabel,
            isTimeOnlyValue: methods.isTimeOnlyValue,
        }

        expect(methods.semesterCourseChips.call(ctx, 1).map((chip: Record<string, string>) => chip.label))
            .toEqual(['F', 'GWB'])
    })

    it('shows Saturday when selected timetable courses contain Saturday values', () => {
        const computed = (Overview as any).computed
        const ctx = {
            activeCourseGroupFilterKeys: ['saturday-course'],
            hasVisibleSaturdayCourses: true,
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            configuredCourseGroups: [
                {
                    key: 'weekday-course',
                    weekday: 1,
                },
                {
                    key: 'saturday-course',
                    weekday: 6,
                },
            ],
        }

        expect(computed.hasVisibleSaturdayCourses.call(ctx)).toBe(true)
        expect(computed.displayedWeekdays.call(ctx).map((weekday: Record<string, string>) => weekday.label))
            .toEqual(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'])
        expect(computed.weekdayRangeLabel.call(ctx)).toBe('Mo-Sa')
    })

    it('hides Saturday when there are no selected Saturday values', () => {
        const computed = (Overview as any).computed
        const ctx = {
            activeCourseGroupFilterKeys: [],
            hasVisibleSaturdayCourses: false,
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            configuredCourseGroups: [
                {
                    key: 'saturday-course',
                    weekday: 6,
                },
            ],
        }

        expect(computed.hasVisibleSaturdayCourses.call(ctx)).toBe(false)
        expect(computed.displayedWeekdays.call(ctx).map((weekday: Record<string, string>) => weekday.label))
            .toEqual(['Mo', 'Di', 'Mi', 'Do', 'Fr'])
        expect(computed.weekdayRangeLabel.call(ctx)).toBe('Mo-Fr')
    })

    it('sizes the timetable grid to the displayed weekday count', () => {
        const computed = (Overview as any).computed
        const ctx = {
            displayedWeekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
            ],
        }

        expect(computed.timetableGridStyle.call(ctx)).toEqual({
            '--overview-timetable-weekdays': 5,
        })

        ctx.displayedWeekdays = [
            ...ctx.displayedWeekdays,
            { label: 'Sa', value: 6 },
        ]

        expect(computed.timetableGridStyle.call(ctx)).toEqual({
            '--overview-timetable-weekdays': 6,
        })
    })

    it('shows no timetable courses by default and filters cells to selected menu items', () => {
        const methods = (Overview as any).methods
        const ethCourseGroup = {
            key: 'eth-1',
            semester: 1,
            weekday: 1,
            hour: 1,
            display_label: 'ETH1 - 1RU - PLOEC',
        }
        const ethDuplicateCourseGroup = {
            key: 'eth-1-copy',
            semester: 1,
            weekday: 1,
            hour: 1,
            display_label: 'ETH1 - 1RU - PLOEC',
        }
        const gwbCourseGroup = {
            key: 'gwb',
            semester: 1,
            weekday: 1,
            hour: 1,
            display_label: 'GWB - 1A - HUB',
        }
        const ctx = {
            activeCourseGroupFilterKeys: [],
            courseGroupsByCell: {
                '1-1-1': [ethCourseGroup, ethDuplicateCourseGroup, gwbCourseGroup],
            },
            courseCellKey: methods.courseCellKey,
            courseGroupSortLabel: methods.courseGroupSortLabel,
            courseGroupsForCell: methods.courseGroupsForCell,
            courseGroupMatchesSelectedRecurrenceWeek: () => true,
            courseMenuEntryKeys: methods.courseMenuEntryKeys,
            isCourseMenuEntryFilterActive: methods.isCourseMenuEntryFilterActive,
        }
        const ethMenuEntry = {
            label: 'ETH1 - 1RU - PLOEC',
            courseGroups: [ethCourseGroup, ethDuplicateCourseGroup],
            courseGroupKeys: ['eth-1', 'eth-1-copy'],
        }
        const gwbMenuEntry = {
            label: 'GWB - 1A - HUB',
            courseGroups: [gwbCourseGroup],
            courseGroupKeys: ['gwb'],
        }

        expect(methods.courseGroupsForCell.call(ctx, 1, 1, 1)).toEqual([])

        methods.toggleCourseMenuEntryFilter.call(ctx, ethMenuEntry)

        expect(ctx.activeCourseGroupFilterKeys).toEqual(['eth-1', 'eth-1-copy'])
        expect(methods.courseGroupsForCell.call(ctx, 1, 1, 1)).toEqual([ethCourseGroup, ethDuplicateCourseGroup])

        methods.toggleCourseMenuEntryFilter.call(ctx, gwbMenuEntry)

        expect(ctx.activeCourseGroupFilterKeys).toEqual(['eth-1', 'eth-1-copy', 'gwb'])
        expect(methods.courseGroupsForCell.call(ctx, 1, 1, 1))
            .toEqual([ethCourseGroup, ethDuplicateCourseGroup, gwbCourseGroup])

        methods.toggleCourseMenuEntryFilter.call(ctx, ethMenuEntry)

        expect(ctx.activeCourseGroupFilterKeys).toEqual(['gwb'])
        expect(methods.courseGroupsForCell.call(ctx, 1, 1, 1)).toEqual([gwbCourseGroup])

        methods.toggleCourseMenuEntryFilter.call(ctx, gwbMenuEntry)

        expect(ctx.activeCourseGroupFilterKeys).toEqual([])
        expect(methods.courseGroupsForCell.call(ctx, 1, 1, 1)).toEqual([])
    })

    it('shows week selectors for selected recurring courses and filters the timetable week', () => {
        const methods = (Overview as any).methods
        const weekOneCourseGroup = {
            key: 'bio-week-1',
            semester: 1,
            weekday: 2,
            hour: 2,
            course: 'BIO',
            title: 'BIO',
            display_label: 'BIO - 1A - CD',
            subject: 'BIO',
            recurrence_interval: 2,
            first_date: '2026-09-08',
            dates: ['2026-09-08', '2026-09-22'],
        }
        const weekTwoCourseGroup = {
            key: 'bio-week-2',
            semester: 1,
            weekday: 2,
            hour: 2,
            course: 'BIO',
            title: 'BIO',
            display_label: 'BIO - 1A - CD',
            subject: 'BIO',
            recurrence_interval: 2,
            first_date: '2026-09-15',
            dates: ['2026-09-15', '2026-09-29'],
        }
        const weeklyCourseGroup = {
            key: 'inf-weekly',
            semester: 1,
            weekday: 4,
            hour: 4,
            course: 'INF',
            title: 'INF',
            display_label: 'INF2 - 4QS+7K - KRO',
            subject: 'INF',
            recurrence_interval: 1,
            first_date: '2026-09-10',
            dates: ['2026-09-10', '2026-09-17', '2026-09-24'],
        }
        const fourWeekCourseGroup = {
            key: 'art-week-1',
            semester: 1,
            weekday: 1,
            hour: 1,
            course: 'ART',
            title: 'ART',
            display_label: 'ART - 1A - GH',
            subject: 'ART',
            recurrence_interval: 4,
            first_date: '2026-09-09',
            dates: ['2026-09-09', '2026-10-07'],
        }
        const blockCourseGroup = {
            key: 'chem-block',
            semester: 1,
            weekday: 3,
            hour: 3,
            course: 'CHEM',
            title: 'CHEM',
            display_label: 'CHEM - 1A - EF',
            subject: 'CHEM',
            recurrence_interval: null,
            is_block: true,
            first_date: '2026-09-30',
            last_date: '2026-09-30',
            dates: ['2026-09-30'],
        }
        const ctx = {
            activeCourseGroupFilterKeys: ['bio-week-1', 'bio-week-2', 'inf-weekly', 'art-week-1', 'chem-block'],
            selectedRecurrenceWeeks: {
                1: 'all_dates',
            },
            expandedRecurrenceWeeks: {
                1: false,
            },
            showExtraDatesInSelectedWeeks: {
                1: false,
            },
            selectedSchoolyear: {
                from: '2026-09-07',
                sem_2_start: '2027-02-15',
            },
            configuredCourseGroups: [
                weekOneCourseGroup,
                weekTwoCourseGroup,
                weeklyCourseGroup,
                fourWeekCourseGroup,
                blockCourseGroup,
            ],
            configuredSchoolHours: [
                { hour: 2, from: '08:50:00', until: '09:35:00' },
            ],
            courseGroupsByCell: {
                '1-2-2': [weekOneCourseGroup, weekTwoCourseGroup],
                '1-4-4': [weeklyCourseGroup],
                '1-1-1': [fourWeekCourseGroup],
                '1-3-3': [blockCourseGroup],
            },
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            courseCellKey: methods.courseCellKey,
            courseGroupSortLabel: methods.courseGroupSortLabel,
            courseGroupsForCell: methods.courseGroupsForCell,
            courseGroupMatchesSelectedRecurrenceWeek: methods.courseGroupMatchesSelectedRecurrenceWeek,
            recurrenceWeekOptions: methods.recurrenceWeekOptions,
            buildRecurrenceWeekOptions: methods.buildRecurrenceWeekOptions,
            timetableSelectorOptions: methods.timetableSelectorOptions,
            allDatesOption: methods.allDatesOption,
            allWeeksOption: methods.allWeeksOption,
            selectedRecurrenceWeek: methods.selectedRecurrenceWeek,
            selectedTimetableOptionValue: methods.selectedTimetableOptionValue,
            setSelectedTimetableOptionValue: methods.setSelectedTimetableOptionValue,
            setSelectedRecurrenceWeek: methods.setSelectedRecurrenceWeek,
            areRecurrenceWeeksExpanded: methods.areRecurrenceWeeksExpanded,
            toggleRecurrenceWeeks: methods.toggleRecurrenceWeeks,
            visibleTimetableWeeks: methods.visibleTimetableWeeks,
            extraDatesOptions: methods.extraDatesOptions,
            buildExtraDatesOptions: methods.buildExtraDatesOptions,
            shouldShowExtraDatesNotice: methods.shouldShowExtraDatesNotice,
            shouldIncludeExtraDatesInRegularWeek: methods.shouldIncludeExtraDatesInRegularWeek,
            courseGroupHasRegularRecurrence: methods.courseGroupHasRegularRecurrence,
            courseGroupHasExtraDateWeek: methods.courseGroupHasExtraDateWeek,
            courseGroupRecurrenceWeek: methods.courseGroupRecurrenceWeek,
            semesterStartDate: methods.semesterStartDate,
            selectedCourseMenuEntries: methods.selectedCourseMenuEntries,
            buildSelectedCourseMenuEntries: methods.buildSelectedCourseMenuEntries,
            semesterCourseMenus: methods.semesterCourseMenus,
            buildSemesterCourseMenus: methods.buildSemesterCourseMenus,
            courseGroupMenuLabel: methods.courseGroupMenuLabel,
            courseMenuEntryScheduleLabelWithFrequency: methods.courseMenuEntryScheduleLabelWithFrequency,
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
            courseMenuEntryFrequencyLabel: methods.courseMenuEntryFrequencyLabel,
            courseMenuEntrySingleDateCount: methods.courseMenuEntrySingleDateCount,
            weekdayForCourseGroup: methods.weekdayForCourseGroup,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            courseGroupCourseSource: methods.courseGroupCourseSource,
            mainCourseLabel: methods.mainCourseLabel,
            isCourseMenuEntryFilterActive: methods.isCourseMenuEntryFilterActive,
            courseMenuEntryKeys: methods.courseMenuEntryKeys,
            normalizeDate: methods.normalizeDate,
            formatCompactDateValue: methods.formatCompactDateValue,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            formatTimeValue: methods.formatTimeValue,
        }

        expect(methods.recurrenceWeekOptions.call(ctx, 1).map((option: Record<string, string>) => option.label))
            .toEqual(['Woche 1', 'Woche 2', 'Woche 3', 'Woche 4'])
        expect(methods.timetableSelectorOptions.call(ctx, 1).map((option: Record<string, string>) => option.label))
            .toEqual(['Stundenplan', 'Woche 1', 'Woche 2', 'Woche 3', 'Woche 4', 'Zusatzwochen', 'Alle Wochen'])
        expect(methods.selectedTimetableOptionValue.call(ctx, 1)).toBe('all_dates')
        expect(methods.visibleTimetableWeeks.call(ctx, 1).map((option: Record<string, string>) => option.label))
            .toEqual(['Stundenplan'])
        expect(methods.courseGroupsForCell.call(ctx, 1, 2, 2)).toEqual([weekOneCourseGroup, weekTwoCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 4, 4)).toEqual([weeklyCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 3)).toEqual([blockCourseGroup])
        expect(methods.shouldShowExtraDatesNotice.call(ctx, 1, methods.visibleTimetableWeeks.call(ctx, 1)[0])).toBe(true)

        methods.setSelectedTimetableOptionValue.call(ctx, 1, 'extra_dates')

        expect(methods.selectedTimetableOptionValue.call(ctx, 1)).toBe('extra_dates')
        expect(methods.visibleTimetableWeeks.call(ctx, 1).map((option: Record<string, string>) => option.label))
            .toEqual(['Zusatzwochen'])
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 3, methods.visibleTimetableWeeks.call(ctx, 1)[0]))
            .toEqual([blockCourseGroup])

        methods.setSelectedTimetableOptionValue.call(ctx, 1, 2)

        expect(ctx.selectedRecurrenceWeeks[1]).toBe(2)
        expect(methods.courseGroupsForCell.call(ctx, 1, 2, 2)).toEqual([weekTwoCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 4, 4)).toEqual([weeklyCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 3)).toEqual([blockCourseGroup])
        expect(methods.shouldShowExtraDatesNotice.call(ctx, 1, methods.visibleTimetableWeeks.call(ctx, 1)[0])).toBe(true)

        methods.setSelectedTimetableOptionValue.call(ctx, 1, 'all_weeks')

        expect(methods.selectedTimetableOptionValue.call(ctx, 1)).toBe('all_weeks')
        expect(methods.visibleTimetableWeeks.call(ctx, 1).map((option: Record<string, string>) => option.label))
            .toEqual(['Woche 1', 'Woche 2', 'Woche 3', 'Woche 4', 'Zusatzwochen'])
        const expandedWeeks = methods.visibleTimetableWeeks.call(ctx, 1)
        const extraWeek = expandedWeeks[4]

        expect(methods.courseGroupsForCell.call(ctx, 1, 2, 2, expandedWeeks[0])).toEqual([weekOneCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 2, 2, expandedWeeks[1])).toEqual([weekTwoCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 2, 2, expandedWeeks[2])).toEqual([weekOneCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 2, 2, expandedWeeks[3])).toEqual([weekTwoCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 4, 4, expandedWeeks[0])).toEqual([weeklyCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 4, 4, expandedWeeks[1])).toEqual([weeklyCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 4, 4, expandedWeeks[2])).toEqual([weeklyCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 4, 4, expandedWeeks[3])).toEqual([weeklyCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 3, expandedWeeks[0])).toEqual([])
        expect(methods.shouldShowExtraDatesNotice.call(ctx, 1, expandedWeeks[0])).toBe(false)
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 3, extraWeek)).toEqual([blockCourseGroup])
    })

    it('keeps block-like recurring courses in the all dates timetable', () => {
        const methods = (Overview as any).methods
        const recurringBlockCourseGroup = {
            key: 'may-recurring',
            semester: 1,
            weekday: 3,
            hour: 11,
            course: 'MAY',
            title: 'MAY',
            display_label: 'MAY',
            subject: 'MAY',
            recurrence_interval: 4,
            is_block: true,
            first_date: '2026-09-09',
            dates: ['2026-09-09', '2026-10-07', '2026-11-04', '2026-12-02'],
        }
        const extraDateCourseGroup = {
            key: 'may-extra',
            semester: 1,
            weekday: 3,
            hour: 11,
            course: 'MAY',
            title: 'MAY',
            display_label: 'MAY',
            subject: 'MAY',
            recurrence_interval: null,
            is_block: true,
            first_date: '2026-09-16',
            dates: ['2026-09-16'],
        }
        const ctx = {
            activeCourseGroupFilterKeys: ['may-recurring', 'may-extra'],
            selectedRecurrenceWeeks: {
                1: 'all_dates',
            },
            expandedRecurrenceWeeks: {
                1: false,
            },
            showExtraDatesInSelectedWeeks: {
                1: false,
            },
            courseGroupsByCell: {
                '1-3-11': [recurringBlockCourseGroup, extraDateCourseGroup],
            },
            courseCellKey: methods.courseCellKey,
            courseGroupSortLabel: methods.courseGroupSortLabel,
            courseGroupsForCell: methods.courseGroupsForCell,
            courseGroupMatchesSelectedRecurrenceWeek: methods.courseGroupMatchesSelectedRecurrenceWeek,
            selectedTimetableOptionValue: methods.selectedTimetableOptionValue,
            selectedRecurrenceWeek: methods.selectedRecurrenceWeek,
            recurrenceWeekOptions: () => [
                { value: 1, label: 'Woche 1' },
                { value: 2, label: 'Woche 2' },
                { value: 3, label: 'Woche 3' },
                { value: 4, label: 'Woche 4' },
            ],
            extraDatesOptions: () => [{
                key: 'semester-1-extra-dates',
                type: 'extra_dates',
                value: 'extra_dates',
                label: 'Zusatzwochen',
                showLabel: true,
            }],
            areRecurrenceWeeksExpanded: methods.areRecurrenceWeeksExpanded,
            shouldIncludeExtraDatesInRegularWeek: methods.shouldIncludeExtraDatesInRegularWeek,
            courseGroupHasRegularRecurrence: methods.courseGroupHasRegularRecurrence,
            courseGroupHasExtraDateWeek: methods.courseGroupHasExtraDateWeek,
            courseGroupRecurrenceWeek: methods.courseGroupRecurrenceWeek,
            semesterStartDate: () => new Date('2026-09-07T00:00:00'),
            normalizeDate: methods.normalizeDate,
        }

        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 11))
            .toEqual([recurringBlockCourseGroup, extraDateCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 11, ctx.extraDatesOptions()[0])).toEqual([extraDateCourseGroup])
    })

    it('builds recurrence week selector options from the largest selected interval', () => {
        const methods = (Overview as any).methods
        const ctx = {
            buildRecurrenceWeekOptions: methods.buildRecurrenceWeekOptions,
            selectedCourseMenuEntries: () => [
                {
                    courseGroups: [
                        { recurrence_interval: 3 },
                    ],
                },
            ],
        }

        expect(methods.recurrenceWeekOptions.call(ctx, 1).map((option: Record<string, string>) => option.label))
            .toEqual(['Woche 1', 'Woche 2', 'Woche 3'])

        ctx.selectedCourseMenuEntries = () => [
            {
                courseGroups: [
                    { recurrence_interval: 4 },
                ],
            },
        ]

        expect(methods.recurrenceWeekOptions.call(ctx, 1).map((option: Record<string, string>) => option.label))
            .toEqual(['Woche 1', 'Woche 2', 'Woche 3', 'Woche 4'])
    })

    it('falls back to the display label when no course field is available', () => {
        const methods = (Overview as any).methods
        const ctx = {
            courseGroupCourseSource: methods.courseGroupCourseSource,
            isTimeOnlyValue: methods.isTimeOnlyValue,
        }

        expect(methods.mainCourseLabel.call(ctx, {
            display_label: 'ETH1 - 1RU - PLOEC',
        })).toBe('ETH')
    })

    it('normalizes LET timetable course labels to LPT', () => {
        const methods = (Overview as any).methods
        const ctx = {
            courseGroupBlockLabel: methods.courseGroupBlockLabel,
            courseGroupCourseSource: methods.courseGroupCourseSource,
            courseGroupDetailLabel: methods.courseGroupDetailLabel,
            courseGroupDisplayLabel: methods.courseGroupDisplayLabel,
            courseGroupDistanceLearning: () => false,
            courseGroupDates: methods.courseGroupDates,
            isTimeOnlyValue: methods.isTimeOnlyValue,
        }
        const courseGroup = {
            course: 'LET',
            display_label: 'LET - 1U - HER',
            recurrence_label: '1-wöchig',
            title: 'LET',
        }

        expect(methods.courseGroupDisplayLabel.call(ctx, courseGroup)).toBe('LPT - 1U - HER')
        expect(methods.courseGroupSingleDateMarkerLabel.call(ctx, courseGroup)).toBe('LPT')
        expect(methods.mainCourseLabel.call(ctx, courseGroup)).toBe('LPT')
        expect(methods.timetablePdfCoursePayload.call(ctx, courseGroup)).toEqual({
            label: 'LPT - 1U - HER',
            details: '1-wöchig',
            dates: [],
            is_fu: false,
        })
    })

    it('marks half-load timetable groups as FU in timetable details and pdf payloads', () => {
        const methods = (Overview as any).methods
        const selectedCourseGroup = {
            key: 'inf1-fu',
            semester: 1,
            weekday: 5,
            hour: 12,
            course: 'INF1',
            class_name: 'INF1-Grp2-KRO',
            display_label: 'INF1-Grp2-KRO',
            recurrence_label: '1-wöchig',
            dates: ['2026-03-13', '2026-03-20'],
        }
        const ctx = {
            activeCourseGroupFilterKeySet: new Set(['inf1-fu']),
            configuredCourseGroups: [
                selectedCourseGroup,
                {
                    key: 'inf1-full-1',
                    semester: 1,
                    weekday: 1,
                    hour: 13,
                    course: 'INF1',
                    class_name: 'INF1-Grp1-KRO',
                    display_label: 'INF1-Grp1-KRO',
                    recurrence_label: '1-wöchig',
                },
                {
                    key: 'inf1-full-2',
                    semester: 1,
                    weekday: 1,
                    hour: 14,
                    course: 'INF1',
                    class_name: 'INF1-Grp1-KRO',
                    display_label: 'INF1-Grp1-KRO',
                    recurrence_label: '1-wöchig',
                },
            ],
            courseAliasesFromValues: methods.courseAliasesFromValues,
            courseCellKey: methods.courseCellKey,
            courseCodeAliases: methods.courseCodeAliases,
            courseCodeAliasParts: methods.courseCodeAliasParts,
            courseCodeTokensFromValue: methods.courseCodeTokensFromValue,
            courseCandidatesForCourseGroup: () => [{ key: 'inf1', code: 'INF1', hours: 2 }],
            courseForCourseGroup: methods.courseForCourseGroup,
            courseGroupChoiceOptionCodes: methods.courseGroupChoiceOptionCodes,
            courseGroupDetailLabel: methods.courseGroupDetailLabel,
            courseGroupDisplayLabel: methods.courseGroupDisplayLabel,
            courseGroupDistanceLearning: methods.courseGroupDistanceLearning,
            courseGroupIsSingleDate: () => false,
            courseGroupMatchesCourse: methods.courseGroupMatchesCourse,
            courseGroupOptionLabel: methods.courseGroupOptionLabel,
            courseGroupPrimaryLabelSegment: methods.courseGroupPrimaryLabelSegment,
            courseGroupsAreDistanceLearningCourse: methods.courseGroupsAreDistanceLearningCourse,
            courseGroupsScheduledWeeklyLoad: methods.courseGroupsScheduledWeeklyLoad,
            courseGroupWeekInterval: methods.courseGroupWeekInterval,
            courseGroupWeeklySlotLoad: methods.courseGroupWeeklySlotLoad,
            courseGroupDates: methods.courseGroupDates,
            dateFromIsoValue: methods.dateFromIsoValue,
            defaultTimetableCodeAlias: methods.defaultTimetableCodeAlias,
            normalizedCourseCode: methods.normalizedCourseCode,
            requiredSlotCountForCourse: methods.requiredSlotCountForCourse,
            timetablePdfCoursePayload: methods.timetablePdfCoursePayload,
            uniqueCourseGroupsBySlot: methods.uniqueCourseGroupsBySlot,
            uniqueValues: methods.uniqueValues,
            weekIntervalFromDates: methods.weekIntervalFromDates,
        }

        expect(methods.courseGroupDistanceLearning.call(ctx, selectedCourseGroup)).toBe(true)
        expect(methods.courseGroupDetailLabel.call(ctx, selectedCourseGroup)).toBe('FU · 1-wöchig')
        expect(methods.timetablePdfCoursePayload.call(ctx, selectedCourseGroup)).toEqual({
            label: 'INF1-Grp2-KRO',
            details: 'FU · 1-wöchig',
            dates: ['2026-03-13', '2026-03-20'],
            is_fu: true,
        })
    })

    it('uses adopted robot FU course group keys in timetable details and pdf payloads', () => {
        const methods = (Overview as any).methods
        const courseGroup = {
            key: 'd2-fu',
            display_label: 'D2 - 2F - ENNS',
            recurrence_label: '2-wöchig',
        }
        const ctx = {
            activeDistanceLearningCourseGroupKeys: ['d2-fu'],
            courseGroupDetailLabel: methods.courseGroupDetailLabel,
            courseGroupDisplayLabel: methods.courseGroupDisplayLabel,
            courseGroupDistanceLearning: methods.courseGroupDistanceLearning,
            courseGroupDates: methods.courseGroupDates,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            timetablePdfCoursePayload: methods.timetablePdfCoursePayload,
        }

        expect(methods.courseGroupDistanceLearning.call(ctx, courseGroup)).toBe(true)
        expect(methods.courseGroupDetailLabel.call(ctx, courseGroup)).toBe('FU · 2-wöchig')
        expect(methods.timetablePdfCoursePayload.call(ctx, courseGroup)).toEqual({
            label: 'D2 - 2F - ENNS',
            details: 'FU · 2-wöchig',
            dates: [],
            is_fu: true,
        })
    })

    it('does not treat time-only labels as main courses', () => {
        const methods = (Overview as any).methods
        const ctx = {
            courseGroupCourseSource: methods.courseGroupCourseSource,
            isTimeOnlyValue: methods.isTimeOnlyValue,
        }

        expect(methods.mainCourseLabel.call(ctx, {
            title: '16:15',
            display_label: '17:05',
            subject: '18:45',
        })).toBe('')
    })

    it('adds the course time range to selected date items', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const ctx = {
            selectedCourseGroup: {
                dates: ['2026-09-07'],
                hour: 9,
                subject: '16:15',
                teacher: '17:05',
            },
            configuredSchoolHours: [
                {
                    hour: 9,
                    from: '16:10:00',
                    until: '16:55:00',
                },
            ],
            selectedCourseGroupDates: ['2026-09-07'],
            formatDateValue: methods.formatDateValue,
            normalizeDate: methods.normalizeDate,
            formatDate: methods.formatDate,
            courseGroupTimeRangeLabel: methods.courseGroupTimeRangeLabel,
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            formatTimeValue: methods.formatTimeValue,
        }

        expect(computed.selectedCourseGroupDateItems.call(ctx)).toEqual([
            {
                key: '2026-09-07-16:15 - 17:05',
                dateLabel: '07.09.2026',
                timeRangeLabel: '16:15 - 17:05',
            },
        ])
    })

    it('uses school hour times when imported times are not available', () => {
        const methods = (Overview as any).methods
        const ctx = {
            configuredSchoolHours: [
                {
                    hour: 2,
                    from: '08:50:00',
                    until: '09:40:00',
                },
            ],
            courseGroupTimeRangeParts: methods.courseGroupTimeRangeParts,
            importedCourseGroupTimeRange: methods.importedCourseGroupTimeRange,
            schoolHourTimeRange: methods.schoolHourTimeRange,
            isTimeOnlyValue: methods.isTimeOnlyValue,
            formatTimeValue: methods.formatTimeValue,
        }

        expect(methods.courseGroupTimeRangeLabel.call(ctx, {
            hour: 2,
            subject: 'GWB',
            teacher: 'ABC',
        })).toBe('08:50 - 09:40')
    })

    it('persists and resets the last timetable view state per schoolyear', () => {
        const methods = (Overview as any).methods
        const clearGeneratedTimetables = vi.fn()
        const resetAdditionalCourseSelection = vi.fn()
        const resetCourseSelection = vi.fn()
        const ctx = {
            selectedSchoolyear: {
                id: 42,
            },
            wizardPanelOpen: true,
            wizardPanelMounted: true,
            manualPanelOpen: true,
            manualPanelSource: 'wizard',
            infoDialogOpen: true,
            settingsDialogOpen: true,
            courseMenuDialog: true,
            courseGroupDialog: true,
            studentDialogOpen: true,
            selectionDialogOpen: true,
            selectedCourseMenuKey: 'menu-1',
            selectedCourseGroup: { key: 'group-1' },
            $refs: {
                wizardCourseCards: {
                    clearGeneratedTimetables,
                    resetAdditionalCourseSelection,
                    resetCourseSelection,
                    showCourseActionAfterCourseInteraction: vi.fn(),
                },
            },
            activeCourseGroupFilterKeys: ['bio-1', 'bio-1', 'inf-1'],
            activeDistanceLearningCourseGroupKeys: ['inf-1', 'inf-1'],
            selectedRecurrenceWeeks: {
                1: 2,
                2: 'extra_dates',
            },
            expandedRecurrenceWeeks: {
                1: true,
                2: false,
            },
            showExtraDatesInSelectedWeeks: {
                1: true,
                2: false,
            },
            restrictCourseChoiceBySelection: true,
            courseChoiceRestrictionMode: 'planned_additional',
            selection: {
                semester: 6,
                religion: 'ETH',
                language: 'S',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            selectionDraft: {
                semester: 1,
                religion: 'Rk',
                language: 'L',
                branch: 'gymnasial',
                artsSubject: 'BE',
            },
            transferredStudentContext: {
                student: {
                    studentCode: '100',
                    label: '4Q · GRASSL Tobias · Semester 6',
                    semesterLabel: 'Semester 6',
                    religion: 'Rk',
                },
                courses: {
                    completed: [{ key: 'ETH1', label: 'ETH1', meta: '1' }],
                    missing: [{ key: 'CH2', label: 'CH2', meta: '3 Std.' }],
                    planned: [{ key: 'S5', label: 'S5', meta: '4 Std.' }],
                    additional: [{ key: 'PP2', label: 'PP2', meta: '2 Std.' }],
                },
            },
            defaultTimetableState: methods.defaultTimetableState,
            defaultSelection: methods.defaultSelection,
            currentTimetableState: methods.currentTimetableState,
            applyTimetableState: methods.applyTimetableState,
            resetTimetablePanels: methods.resetTimetablePanels,
            normalizedSelection: methods.normalizedSelection,
            normalizedCourseChoiceRestrictionMode: methods.normalizedCourseChoiceRestrictionMode,
            normalizedTransferredStudentContext: methods.normalizedTransferredStudentContext,
            normalizedTransferredStudentCourses: methods.normalizedTransferredStudentCourses,
            timetableStorageKey: methods.timetableStorageKey,
            robotTimetableStorageKey: methods.robotTimetableStorageKey,
            robotTimetableStorageKeys: methods.robotTimetableStorageKeys,
            timetableStorage: methods.timetableStorage,
            saveLastTimetableState: methods.saveLastTimetableState,
            restoreLastTimetableState: methods.restoreLastTimetableState,
            removeSavedTimetableState: methods.removeSavedTimetableState,
            removeSavedRobotTimetableState: methods.removeSavedRobotTimetableState,
            savedRobotTimetableStateAvailable: methods.savedRobotTimetableStateAvailable,
            runTimetableUpdate(action: () => void) {
                action()
            },
        }

        window.localStorage.clear()

        methods.saveLastTimetableState.call(ctx)
        window.localStorage.setItem(methods.robotTimetableStorageKey.call(ctx), JSON.stringify({
            selectedAdditionalCourseKeys: ['INF2'],
        }))
        window.localStorage.setItem(methods.robotTimetableStorageKey.call(ctx, 'default'), JSON.stringify({
            selectedAdditionalCourseKeys: ['INF2'],
        }))

        ctx.activeCourseGroupFilterKeys = []
        ctx.selectedRecurrenceWeeks = {
            1: 'all_dates',
            2: 'all_dates',
        }
        ctx.expandedRecurrenceWeeks = {
            1: false,
            2: false,
        }
        ctx.showExtraDatesInSelectedWeeks = {
            1: false,
            2: false,
        }
        ctx.manualPanelOpen = false
        ctx.manualPanelSource = null
        ctx.restrictCourseChoiceBySelection = false
        ctx.courseChoiceRestrictionMode = 'all'
        ctx.selection = methods.defaultSelection.call(ctx)
        ctx.selectionDraft = methods.defaultSelection.call(ctx)
        ctx.transferredStudentContext = null

        methods.restoreLastTimetableState.call(ctx)

        expect(ctx.activeCourseGroupFilterKeys).toEqual(['bio-1', 'inf-1'])
        expect(ctx.activeDistanceLearningCourseGroupKeys).toEqual(['inf-1'])
        expect(ctx.selectedRecurrenceWeeks).toEqual({
            1: 2,
            2: 'extra_dates',
        })
        expect(ctx.expandedRecurrenceWeeks).toEqual({
            1: true,
            2: false,
        })
        expect(ctx.showExtraDatesInSelectedWeeks).toEqual({
            1: true,
            2: false,
        })
        expect(ctx.manualPanelOpen).toBe(true)
        expect(ctx.manualPanelSource).toBe('wizard')
        expect(methods.savedRobotTimetableStateAvailable.call(ctx)).toBe(true)
        expect(ctx.restrictCourseChoiceBySelection).toBe(true)
        expect(ctx.courseChoiceRestrictionMode).toBe('planned_additional')
        expect(ctx.selection).toEqual({
            semester: 6,
            religion: 'ETH',
            language: 'S',
            branch: 'wirtschaftskundlich',
            artsSubject: 'ME',
        })
        expect(ctx.selectionDraft).toEqual(ctx.selection)
        expect(ctx.transferredStudentContext?.student.label).toBe('4Q · GRASSL Tobias · Semester 6')
        expect(ctx.transferredStudentContext?.student.religion).toBe('Rk')
        expect(ctx.transferredStudentContext?.courses.missing).toEqual([
            {
                key: 'CH2',
                code: '',
                name: '',
                label: 'CH2',
                meta: '3 Std.',
            },
        ])

        methods.resetSavedTimetable.call(ctx)

        expect(window.localStorage.getItem(methods.timetableStorageKey.call(ctx))).toBeNull()
        expect(window.localStorage.getItem(methods.robotTimetableStorageKey.call(ctx))).toBeNull()
        expect(window.localStorage.getItem(methods.robotTimetableStorageKey.call(ctx, 'default'))).toBeNull()
        expect(methods.savedRobotTimetableStateAvailable.call(ctx)).toBe(false)
        expect(resetCourseSelection).toHaveBeenCalled()
        expect(resetAdditionalCourseSelection).toHaveBeenCalled()
        expect(clearGeneratedTimetables).toHaveBeenCalled()
        expect(ctx.wizardPanelOpen).toBe(false)
        expect(ctx.wizardPanelMounted).toBe(false)
        expect(ctx.manualPanelOpen).toBe(false)
        expect(ctx.manualPanelSource).toBeNull()
        expect(ctx.infoDialogOpen).toBe(false)
        expect(ctx.settingsDialogOpen).toBe(false)
        expect(ctx.courseMenuDialog).toBe(false)
        expect(ctx.courseGroupDialog).toBe(false)
        expect(ctx.studentDialogOpen).toBe(false)
        expect(ctx.selectionDialogOpen).toBe(false)
        expect(ctx.selectedCourseMenuKey).toBe('')
        expect(ctx.selectedCourseGroup).toBeNull()
        expect(ctx.activeCourseGroupFilterKeys).toEqual([])
        expect(ctx.activeDistanceLearningCourseGroupKeys).toEqual([])
        expect(ctx.selectedRecurrenceWeeks).toEqual({
            1: 'all_dates',
            2: 'all_dates',
        })
        expect(ctx.expandedRecurrenceWeeks).toEqual({
            1: false,
            2: false,
        })
        expect(ctx.showExtraDatesInSelectedWeeks).toEqual({
            1: false,
            2: false,
        })
        expect(ctx.restrictCourseChoiceBySelection).toBe(false)
        expect(ctx.courseChoiceRestrictionMode).toBe('all')
        expect(ctx.selection).toEqual({
            semester: 1,
            religion: 'ETH',
            language: 'L',
            branch: 'wirtschaftskundlich',
            artsSubject: 'ME',
        })
        expect(ctx.transferredStudentContext).toBeNull()
        expect(ctx.manualPanelSource).toBeNull()
    })

    it('shows the date range for block course labels', () => {
        const methods = (Overview as any).methods
        const ctx = {
            courseGroupBlockLabel: methods.courseGroupBlockLabel,
            courseGroupDateRangeLabel: methods.courseGroupDateRangeLabel,
            formatCompactDateValue: methods.formatCompactDateValue,
            formatDateValue: methods.formatDateValue,
            normalizeDate: methods.normalizeDate,
            formatDate: methods.formatDate,
        }

        expect(methods.courseGroupBlockLabel.call(ctx, {
            is_block: true,
            block_label: 'Block',
            first_date: '2026-09-07',
            last_date: '2026-10-12',
            dates: ['2026-09-07', '2026-09-14', '2026-10-12'],
        })).toBe('07.09. - 12.10.')
    })

    it('renders the overview timetable with the robot-style generated grid', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/overview/Overview.vue',
            'utf8',
        )

        expect(componentSource).toContain('class="timetable-generated-grid"')
        expect(componentSource).toContain("'timetable-generated-cell--filled'")
        expect(componentSource).toContain("'timetable-generated-cell--conflict'")
        expect(componentSource).toContain("'timetable-generated-cell--related-overlap'")
        expect(componentSource).toContain("'timetable-generated-cell--has-single-date-markers'")
        expect(componentSource).toContain('courseGroupSingleDateOverlapMarkersForCell(semester.value, weekday.value, hour.hour, timetableWeek)')
        expect(componentSource).toContain('prepend-icon="mdi-file-pdf-box"')
        expect(componentSource).toContain('class="recurrence-week-selector__btn recurrence-week-selector__pdf-btn overview-print-hidden"')
        expect(componentSource.indexOf('class="recurrence-week-selector__btn recurrence-week-selector__pdf-btn overview-print-hidden"'))
            .toBeGreaterThan(componentSource.indexOf('class="recurrence-week-selector"'))
        expect(componentSource).toContain('@click="downloadTimetablePdf"')
        expect(componentSource).toContain("axios.post(\n                    '/api/admin/students-timetables/overview/pdf'")
        expect(componentSource).toContain("responseType: 'blob'")
        expect(componentSource).toContain(':loading="pdfExporting"')
        expect(componentSource).toContain('size="large"')
        expect(componentSource).toContain('margin-left: auto;')
        expect(componentSource).toContain('.recurrence-week-selector :deep(.v-btn-toggle) {')
        expect(componentSource).toContain('height: auto;')
        expect(componentSource).toContain('overflow: visible;')
        expect(componentSource).toContain('min-height: 32px;')
        expect(componentSource).toContain('white-space: nowrap;')
        expect(componentSource).toContain('downloadBlob(blob, fileName)')
        expect(componentSource).not.toContain('window.print()')
        expect(componentSource).not.toContain('overview-pdf-printing')
        expect(componentSource).toContain('Zusatzwochen vorhanden')
        expect(componentSource).not.toContain('label="Im aktuellen Stundenplan anzeigen"')
        expect(componentSource).not.toContain('handleShowExtraDatesUpdate')
        expect(componentSource).toContain('Alle Wochen')
        expect(componentSource).not.toContain('Wochen anzeigen')
        expect(componentSource).not.toContain('Eine Woche anzeigen')
        expect(componentSource).not.toContain('recurrence-week-selector__action-btn')
        expect(componentSource).toContain(':title="marker.title"')
        expect(componentSource).toContain('timetable-generated-cell__single-date-marker')
        expect(componentSource).toContain('singleDateOverviewGroupsForSemester(semester.value)')
        expect(componentSource).toContain('class="timetable-date-overview timetable-single-date-overview"')
        expect(componentSource).toContain('Einzeltermine')
        expect(componentSource).toContain('timetable-single-date-overview__item')
        expect(componentSource).toContain(':style="timetableGridStyle"')
        expect(componentSource).toContain('hasVisibleSaturdayCourses')
        expect(componentSource).not.toContain('class="timetable-saturday-toggle"')
        expect(componentSource).not.toContain('aria-label="Samstag anzeigen"')
        expect(componentSource).not.toContain('timetable-generated-cell--saturday-placeholder')
        expect(componentSource).toContain('grid-template-columns: 64px repeat(var(--overview-timetable-weekdays, 5), minmax(62px, 1fr));')
        expect(componentSource).toContain('grid-template-rows: 34px;')
        expect(componentSource).toContain('grid-auto-rows: minmax(58px, auto);')
        expect(componentSource).toContain('min-height: 48px;')
        expect(componentSource).toContain('text-overflow: ellipsis;')
        expect(componentSource).toContain('background: #bbf7d0;')
        expect(componentSource).not.toContain('class="timetable-hour-column"')
        expect(componentSource).not.toContain('class="timetable-day-column"')
        expect(componentSource).not.toContain('table-layout: fixed;')
        expect(componentSource).toContain('overflow-wrap: anywhere;')
        expect(componentSource).toContain('class="course-choice-panel"')
        expect(componentSource).toContain('icon="mdi-plus"')
        expect(componentSource).toContain(':student-code="transferredStudentContext?.student?.studentCode || null"')
        expect(componentSource).toContain('<v-dialog v-model="courseMenuDialog" persistent')
        expect(componentSource).toContain('allCourseChoiceMenus')
        expect(componentSource).toContain('selectedCourseMenu')
        expect(componentSource).toContain(":color=\"courseMenuHasActiveSelection(courseMenu) ? 'success' : 'primary'\"")
        expect(componentSource).toContain('courseMenuHasActiveSelection(courseMenu)')
        expect(componentSource).toContain('class="course-item-chips"')
        expect(componentSource).toContain('class="course-choice-panel__semesters course-menu-dialog-items"')
        expect(componentSource).toContain('.course-menu-dialog-items .course-item-chips')
        expect(componentSource).toContain('grid-template-columns: minmax(0, 1fr);')
        expect(componentSource).toContain('visibleCourseChoiceSemesters')
        expect(componentSource).toContain('restrictCourseChoiceBySelection')
        expect(componentSource).toContain('courseChoiceRestrictionMode')
        expect(componentSource).toContain('courseChoiceRestrictionOptions')
        expect(componentSource).toContain('Vorgesehene Kurse')
        expect(componentSource).toContain('Zusätzliche Kurse')
        expect(componentSource).toContain('Alle')
        expect(componentSource).toContain('<v-btn-toggle')
        expect(componentSource).toContain('class="course-menu-dialog-options"')
        expect(componentSource.indexOf('class="course-menu-dialog-options"'))
            .toBeGreaterThan(componentSource.indexOf('<v-dialog v-model="courseMenuDialog" persistent'))
        expect(componentSource).toContain('@update:model-value="handleCourseChoiceRestrictionUpdate"')
        expect(componentSource).toContain('courseGroupMatchesCourseChoiceRestriction(courseGroup, courseRestrictionContext)')
        expect(componentSource).toContain('selectionCourseChoiceCodes')
        expect(componentSource).toContain('shouldRestrictCourseChoiceByTimetableSemester')
        expect(componentSource).toContain('restrictedStudentCourseCodes')
        expect(componentSource).toContain('course-choice-semester__dates')
        expect(componentSource).toContain('grid-template-columns: minmax(0, 1fr);')
        expect(componentSource).toContain('selectedCourseFilterChipsAll')
        expect(componentSource).toContain("filterChip.hasBlockingOverlap ? 'error' : filterChip.hasRelatedOverlap ? 'warning' : 'success'")
        expect(componentSource).toContain('selectedCourseMenuEntryOptions')
        expect(componentSource).toContain(':color="entryOption.color"')
        expect(componentSource).toContain('selectedCourseCount')
        expect(componentSource).toContain('v-model="studentDialogOpen"')
        expect(componentSource).toContain('ref="studentSearchField"')
        expect(componentSource).toContain('v-model="studentSearch"')
        expect(componentSource).toContain('label="Student suchen"')
        expect(componentSource).toContain('@keydown.enter.prevent="submitStudentSearch"')
        expect(componentSource).toContain('@click.stop="clearTransferredStudentSelection"')
        expect(componentSource).toContain('class="overview-student-inline-actions"')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/robot/students')")
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/robot/student-overview'")
        expect(componentSource).not.toContain("axios.get('/api/admin/students-timetables/robot/student-completed-courses'")
        expect(componentSource).toContain('transferredStudentLabel')
        expect(componentSource).toContain('Kein Student')
        expect(componentSource).toContain('class="overview-selection"')
        expect(componentSource).toContain('class="overview-selected-card"')
        expect(componentSource).toContain('class="overview-selected-card__meta"')
        expect(componentSource).toContain('transferredStudentReligionMeta')
        expect(componentSource).toContain('Religion: ${value}')
        expect(componentSource).toContain('class="overview-context-card"')
        expect(componentSource).toContain('class="overview-wizard-button"')
        const wizardButtonSource = componentSource.slice(
            componentSource.indexOf('class="overview-wizard-button"'),
            componentSource.indexOf('</v-btn>', componentSource.indexOf('class="overview-wizard-button"')),
        )
        expect(wizardButtonSource).not.toContain('block')
        expect(wizardButtonSource).toContain('variant="tonal"')
        expect(wizardButtonSource).toContain('color="primary"')
        expect(wizardButtonSource).toContain('prepend-icon="mdi-calendar-clock"')
        expect(wizardButtonSource).not.toContain('append-icon')
        expect(componentSource).toContain(':active="wizardPanelOpen"')
        expect(componentSource).toContain('@click="openWizardPanel"')
        expect(componentSource).toContain("const TIMETABLE_OVERVIEW_BASE_PATH = '/admin/students-timetables/timetable/overview'")
        expect(componentSource).toContain("const TIMETABLE_OVERVIEW_LANDING_PATH = '/admin/students-timetables'")
        expect(componentSource).toContain("const TIMETABLE_OVERVIEW_ROUTE_MODES = ['automatic', 'manual', 'adopted']")
        expect(componentSource).toContain("'$route.params.detail'(detail)")
        expect(componentSource).toContain('applyTimetableOverviewModeFromRoute(detail)')
        expect(componentSource).toContain('applyTimetableOverviewLandingState()')
        expect(componentSource).toContain('<span>Automatischer</span>')
        expect(componentSource).toContain('<span>Stundenplan</span>')
        expect(componentSource).toContain('class="overview-active-label overview-active-label--auto"')
        expect(componentSource).toContain('Automatischer Stundenplan')
        expect(componentSource).toContain('RobotTimetable')
        expect(componentSource).toContain('v-if="wizardPanelMounted"')
        expect(componentSource).toContain('v-show="wizardPanelOpen"')
        expect(componentSource).toContain('class="overview-wizard-course-cards-panel"')
        expect(componentSource).toContain('ref="wizardCourseCards"')
        expect(componentSource).toContain('embedded-course-cards-only')
        expect(componentSource).toContain('class="overview-wizard-course-cards"')
        expect(componentSource).toContain('<template #course-actions="{ ready, loading, extending, createActionVisible, hasSelectedAdditionalCourses, extensionActionVisible }">')
        expect(componentSource).toContain('v-if="!wizardPanelOpen && !manualPanelOpen"')
        expect(componentSource).toContain('class="overview-manual-button"')
        expect(componentSource).toContain(':active="directManualPanelOpen"')
        expect(componentSource).toContain('@click="openManualPanel"')
        expect(componentSource).toContain('prepend-icon="mdi-calendar-edit"')
        expect(componentSource).toContain('class="overview-manual-button__label"')
        expect(componentSource).toContain('<span>Manueller</span>')
        expect(componentSource).toContain('<span>Stundenplan</span>')
        expect(componentSource).toContain('Manueller Stundenplan')
        expect(componentSource).toContain('class="overview-active-label overview-active-label--manual"')
        expect(componentSource).toContain('directManualPanelOpen')
        expect(componentSource).toContain('class="overview-wizard-close-button"')
        expect(componentSource).toContain('v-if="manualPanelBackToWizardVisible"')
        expect(componentSource).toContain('Zurück')
        expect(componentSource).toContain('Abbruch')
        expect(componentSource).toContain('overview-wizard-cancel-button')
        expect(componentSource).toContain('margin-left: auto;')
        expect(componentSource).toContain('color="error"')
        expect(componentSource).toContain('prepend-icon="mdi-close-circle-outline"')
        expect(componentSource).toContain('@click="resetSavedTimetable"')
        expect(componentSource).toContain('v-if="wizardPanelOpen || directManualPanelOpen"')
        expect(componentSource).toContain('@click="closeActiveTimetablePanel"')
        expect(componentSource).toContain('Schließen')
        expect(componentSource).toContain('class="overview-wizard-create-button"')
        expect(componentSource).toContain('class="overview-wizard-footer"')
        expect(componentSource).toContain('class="overview-wizard-loading"')
        expect(componentSource).toContain('@click="createWizardTimetable(extending)"')
        expect(componentSource).toContain('v-if="ready && createActionVisible && (!extending || (hasSelectedAdditionalCourses && extensionActionVisible))"')
        expect(componentSource).toContain('v-else-if="loading"')
        expect(componentSource).toContain(':disabled="wizardTimetableCreating"')
        expect(componentSource).toContain(':loading="wizardTimetableCreating"')
        expect(componentSource).toContain('Kurse werden geladen')
        expect(componentSource).toContain('async createWizardTimetable(requireAdditionalCourses = false)')
        expect(componentSource).toContain('requireAdditionalCourses: requireAdditionalCourses === true')
        expect(componentSource).toContain('this.wizardTimetableCreating = true')
        expect(componentSource).toContain("{{ extending ? 'Stundenplan erweitern' : 'Stundenplan erstellen' }}")
        expect(componentSource.indexOf('class="overview-wizard-course-cards"')).toBeLessThan(
            componentSource.indexOf('class="overview-wizard-create-button"'),
        )
        expect(componentSource).toContain('<div v-if="manualPanelOpen" class="course-choice-panel">')
        expect(componentSource).toContain('manualPanelSource === \'wizard\'')
        expect(componentSource).toContain('prepend-icon="mdi-arrow-left"')
        expect(componentSource).not.toContain('class="course-choice-panel__back-row"')
        expect(componentSource).not.toContain('class="course-choice-panel__back-button"')
        expect(componentSource).not.toContain('Zurück zum automatischen Stundenplan')
        expect(componentSource).toContain('v-if="wizardPanelOpen" class="overview-wizard-settings-summary"')
        expect(componentSource).toContain('v-if="wizardPanelOpen" class="overview-wizard-actions"')
        expect(componentSource).toContain('activeEvaluationCriteria')
        expect(componentSource).toContain('Bewertungskriterien')
        expect(componentSource).toContain('Keine Bewertungskriterien aktiv')
        expect(componentSource).toContain('justify-items: end;')
        expect(componentSource).toContain('margin-left: auto;')
        expect(componentSource).toContain('justify-content: flex-end;')
        expect(componentSource).toContain('margin: -6px 0 16px;')
        expect(componentSource).toContain('min-height: 48px;')
        expect(componentSource).toContain('border: 1px solid rgba(var(--v-theme-primary), 0.18);')
        expect(componentSource).toContain('background: rgba(255, 255, 255, 0.72);')
        expect(componentSource).toContain('icon="mdi-cog-outline"')
        expect(componentSource).toContain('@click="settingsDialogOpen = true"')
        expect(componentSource).toContain('icon="mdi-information-outline"')
        expect(componentSource).toContain('@click="infoDialogOpen = true"')
        expect(componentSource).toContain('<v-dialog v-model="infoDialogOpen" persistent max-width="680">')
        expect(componentSource).toContain('Hinweise zum Stundenplan Wizzard')
        expect(componentSource).toContain('<v-dialog v-model="settingsDialogOpen" persistent max-width="1120" scrollable>')
        expect(componentSource).toContain('@changed="onSettingsChanged"')
        expect(componentSource).toContain('@saved="onSettingsSaved"')
        expect(componentSource).toContain(':evaluation-criteria-settings="evaluationCriteria"')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/evaluation-settings')")
        expect(componentSource).not.toContain('WU-Design')
        expect(componentSource).toContain('<v-dialog v-model="selectionDialogOpen" persistent max-width="640">')
        expect(componentSource).toContain('label="Semester"')
        expect(componentSource).toContain('label="Ethik / Religion"')
        expect(componentSource).toContain('label="Sprache"')
        expect(componentSource).toContain('label="Zweig"')
        expect(componentSource).toContain('label="ME / BE"')
        expect(componentSource).toContain('selectedSummary')
        expect(componentSource).toContain('openSelectionDialog()')
        expect(componentSource).toContain('updateSelection()')
        expect(componentSource).toContain('transferredStudentContext')
        expect(componentSource).toContain('transferredStudentCourseSections')
        expect(componentSource).toContain('noStudentCourseHistory')
        expect(componentSource).toContain('visibleTransferredStudentCourseSections')
        expect(componentSource).toContain('v-model="expandedTransferredStudentCourseSections"')
        expect(componentSource).toContain('<div v-if="transferredStudentContext" class="transferred-student-course-overview">')
        expect(componentSource).toContain('class="transferred-student-course-panels"')
        expect(componentSource).toContain('class="transferred-student-course-panel"')
        expect(componentSource).toContain('class="transferred-student-course-panel__title"')
        expect(componentSource).not.toContain('transferredStudentContextExpanded')
        expect(componentSource).not.toContain('toggleTransferredStudentContext')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/subjects-overview-settings')")
        expect(componentSource).toContain('this.loadTransferredStudentCompletedCourses(this.transferredStudentContext.student.studentCode, {')
        expect(componentSource).toContain('includeSelection: true')
        expect(componentSource).toContain('applySelectionDefaults: true')
        expect(componentSource).toContain('applyTransferredStudentSelectionDefaultsFromOverview(overviewSummary)')
        expect(componentSource).toContain('studentOverviewSelectionPayload()')
        expect(componentSource).toContain('overviewStudentCourseHistoryFromSummary(overviewSummary)')
        expect(componentSource).toContain('refreshTransferredStudentCourseHistory()')
        expect(componentSource.indexOf('class="transferred-student-context"')).toBeLessThan(
            componentSource.indexOf('class="overview-selection"'),
        )
        expect(componentSource.indexOf('class="overview-selection"')).toBeLessThan(
            componentSource.indexOf('class="transferred-student-course-overview"'),
        )
        expect(componentSource.indexOf('class="transferred-student-course-overview"')).toBeLessThan(
            componentSource.indexOf('class="course-choice-panel"'),
        )
        expect(componentSource.indexOf('class="course-choice-panel"')).toBeLessThan(
            componentSource.indexOf('class="overview-context-card"'),
        )
        expect(componentSource).not.toContain('<v-expand-transition>')
        expect(componentSource).toContain('return this.transferredStudentCourseSections')
        expect(componentSource).toContain('Abgeschlossene Kurse')
        expect(componentSource).toContain('mdi-check-circle-outline')
        expect(componentSource).toContain('Fehlende Kurse')
        expect(componentSource).toContain('mdi-alert-circle-outline')
        expect(componentSource).toContain('Vorgesehene Kurse')
        expect(componentSource).toContain('mdi-format-list-checks')
        expect(componentSource).toContain('Zusätzliche Kurse')
        expect(componentSource).toContain('mdi-plus-circle-outline')
        expect(componentSource).not.toContain('v-for="courseMenu in semesterCourseMenus(semester.value)"')
        expect(componentSource).toContain('class="semester-timetable"')
        expect(componentSource).toContain('v-if="!wizardPanelOpen && visibleTimetableSemesters.length"')
        expect(componentSource).toContain('sameSlotDateOverviewGroupsForSemester(semester.value)')
        expect(componentSource).toContain('class="timetable-date-overview"')
        expect(componentSource).toContain('Termine in gleichen Zellen')
        expect(componentSource).toContain('class="timetable-date-overview__date"')
        expect(componentSource).not.toContain('Wähle oben Kurse aus, um den Stundenplan anzuzeigen.')
        expect(componentSource).toContain("label: 'Semester'")
        expect(componentSource).not.toContain("label: 'Semester 1'")
        expect(componentSource).not.toContain("label: 'Semester 2'")
        expect(componentSource).not.toContain('class="semester-section"')
        expect(componentSource).not.toContain("axios.get('/api/admin/students-timetables/overview-selections')")
        expect(componentSource).not.toContain("axios.put('/api/admin/students-timetables/overview-selections'")
    })

    it('keeps transferred student future courses available for prerequisite filtering', () => {
        const methods = (Overview as any).methods
        const ctx = {
            subjectRows: [
                { semester: 3, code: 'M3' },
                { semester: 4, code: 'M4' },
                { semester: 5, code: 'M5' },
                { semester: 6, code: 'M6' },
            ],
            coursesForSemester(semester: number) {
                return this.subjectRows
                    .filter(subject => subject.semester === semester)
                    .map(subject => subject.code)
            },
        }

        expect(methods.coursesAfterSemester.call(ctx, 4)).toEqual(['M5', 'M6'])
    })

    it('allows second modules as transferred student additional courses without completed first modules', () => {
        const methods = (Overview as any).methods
        const completedCourseCodes = new Set()
        const plannedCourseCodes = new Set(['M1'])
        const visitedCourseCodes = new Set()
        const ctx = {
            studentCourseBaseAliases: methods.studentCourseBaseAliases,
            normalizedCourseCode: methods.normalizedCourseCode,
            uniqueValues: methods.uniqueValues,
        }

        expect(methods.courseModulePrerequisiteMet.call(
            ctx,
            { base: 'M', module: '2' },
            completedCourseCodes,
            visitedCourseCodes,
            { plannedCourseCodes },
        )).toBe(true)
    })

    it('shows only course-choice semesters that have courses', () => {
        const computed = (Overview as any).computed

        const ctx = {
            semesters: [
                { value: 1, label: 'Semester', dateRangeLabel: '08.09.2025 - 15.02.2026' },
                { value: 2, label: 'Semester', dateRangeLabel: '16.02.2026 - 10.07.2026' },
            ],
            semesterCourseMenus(semester: number) {
                return semester === 2 ? [{ key: 'kg' }] : []
            },
        }

        expect(computed.visibleCourseChoiceSemesters.call(ctx)).toEqual([
            { value: 2, label: 'Semester', dateRangeLabel: '16.02.2026 - 10.07.2026' },
        ])
    })

    it('restricts course choice menus by student and selected options when enabled', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            ...methods,
            restrictCourseChoiceBySelection: true,
            courseChoiceRestrictionMode: 'planned_additional',
            selection: {
                semester: 6,
                religion: 'ETH',
                language: 'S',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            configuredSchoolHours: [],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            configuredCourseGroups: [
                {
                    key: 'eth-2',
                    semester: 2,
                    weekday: 1,
                    hour: 1,
                    course: 'ETH2',
                    display_label: 'ETH2 - 2AF - PLOEC',
                    subject: 'ETH',
                },
                {
                    key: 'rk-2',
                    semester: 2,
                    weekday: 1,
                    hour: 2,
                    course: 'Rk2',
                    display_label: 'Rk2 - 2AF - ABC',
                    subject: 'Rk',
                },
                {
                    key: 's-4',
                    semester: 2,
                    weekday: 2,
                    hour: 3,
                    course: 'S4',
                    display_label: 'SPA4 - KOR',
                    subject: 'SPA',
                },
                {
                    key: 'f-4',
                    semester: 2,
                    weekday: 2,
                    hour: 4,
                    course: 'F4',
                    display_label: 'F4 - ABC',
                    subject: 'F',
                },
                {
                    key: 'ch-2',
                    semester: 2,
                    weekday: 3,
                    hour: 5,
                    course: 'CH2',
                    display_label: 'CH2 - 5K - PLA',
                    subject: 'CH',
                },
                {
                    key: 'd-6',
                    semester: 2,
                    weekday: 4,
                    hour: 6,
                    course: 'D6',
                    display_label: 'D6 - ABC',
                    subject: 'D',
                },
                {
                    key: 's-4-wrong-semester',
                    semester: 1,
                    weekday: 5,
                    hour: 7,
                    course: 'S4',
                    display_label: 'SPA4 - KOR',
                    subject: 'SPA',
                },
            ],
            transferredStudentContext: {
                courses: {
                    missing: [{ code: 'CH2', label: 'CH2' }],
                    planned: [{ code: 'S4', label: 'S4' }],
                    additional: [{ code: 'ETH2', label: 'ETH2' }],
                },
            },
        }

        Object.defineProperty(ctx, 'courseChoiceCourseGroups', {
            get() {
                return computed.courseChoiceCourseGroups.call(this)
            },
        })
        Object.defineProperty(ctx, 'restrictedStudentCourseCodes', {
            get() {
                return computed.restrictedStudentCourseCodes.call(this)
            },
        })
        const restrictedMenus = methods.buildSemesterCourseMenus.call(ctx, 2)

        expect(restrictedMenus.map((courseMenu: Record<string, string>) => courseMenu.label))
            .toEqual(['ETH', 'S'])
        expect(methods.buildSemesterCourseMenus.call(ctx, 1)).toEqual([])

        ctx.courseChoiceRestrictionMode = 'planned'
        expect(methods.buildSemesterCourseMenus.call(ctx, 2).map((courseMenu: Record<string, string>) => courseMenu.label))
            .toEqual(['S'])

        ctx.courseChoiceRestrictionMode = 'all'
        expect(methods.buildSemesterCourseMenus.call(ctx, 2).map((courseMenu: Record<string, string>) => courseMenu.label))
            .toEqual(['CH', 'D', 'ETH', 'F', 'Rk', 'S'])
    })

    it('keeps additional student courses visible when restricting course choices', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            ...methods,
            restrictCourseChoiceBySelection: true,
            courseChoiceRestrictionMode: 'planned_additional',
            selection: {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            configuredSchoolHours: [],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            subjectRows: [
                { id: 1, is_active: true, semester: 1, json_subject: 'D', json_code: 'D1', name: 'Deutsch 1', branch: 'common', hours_per_week: 3 },
                { id: 2, is_active: true, semester: 2, json_subject: 'M', json_code: 'M2', name: 'Mathematik 2', branch: 'common', hours_per_week: 4 },
                { id: 3, is_active: true, semester: 1, json_subject: 'E', json_code: 'E1', name: 'Englisch 1', branch: 'common', hours_per_week: 4 },
            ],
            configuredCourseGroups: [
                { key: 'd-1', semester: 1, weekday: 1, hour: 1, course: 'D1', display_label: 'D1 - ABC', subject: 'D' },
                { key: 'e-1', semester: 1, weekday: 1, hour: 2, course: 'E1', display_label: 'E1 - NIE', subject: 'E' },
                { key: 'm-2', semester: 2, weekday: 2, hour: 1, course: 'M', module_code: 'M2', display_label: 'M - ALT', subject: 'M' },
                { key: 'm-1', semester: 1, weekday: 2, hour: 1, course: 'M', module_code: 'M1', display_label: 'M - HER', subject: 'M' },
                { key: 'm-3', semester: 1, weekday: 2, hour: 3, course: 'M', display_label: 'M3 - FUCH', subject: 'M' },
                { key: 'm-4', semester: 2, weekday: 2, hour: 4, course: 'M', display_label: 'M4 - SCHM', subject: 'M' },
                { key: 'e-2', semester: 2, weekday: 2, hour: 2, course: 'E2', display_label: 'E2 - NIE', subject: 'E' },
            ],
            transferredStudentContext: {
                courses: {
                    missing: [],
                    planned: [{ code: 'D1', label: 'D1' }],
                    additional: [{ code: 'M2', label: 'M2' }],
                },
            },
        }

        Object.defineProperty(ctx, 'courseChoiceCourseGroups', {
            get() {
                return computed.courseChoiceCourseGroups.call(this)
            },
        })
        Object.defineProperty(ctx, 'restrictedStudentCourseCodes', {
            get() {
                return computed.restrictedStudentCourseCodes.call(this)
            },
        })
        expect(methods.buildSemesterCourseMenus.call(ctx, 1).map((courseMenu: Record<string, string>) => courseMenu.label))
            .toEqual(['D'])
        expect(methods.buildSemesterCourseMenus.call(ctx, 2).map((courseMenu: Record<string, string>) => courseMenu.label))
            .toEqual(['M'])
    })

    it('restricts course choices to the transferred student card courses', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const studentCourses = {
            missing: [],
            planned: ['D1', 'E1', 'ETH1', 'GW1', 'INF1', 'LPT', 'M1'].map(code => ({ code, label: code })),
            additional: ['D2', 'E2', 'ETH2', 'GS1', 'GW2', 'L1', 'M2', 'BU1', 'CH1', 'PH1', 'PP1', 'INF2', 'ÖKO1']
                .map(code => ({ code, label: code })),
        }
        const courseGroup = (code: string, semester: number) => ({
            key: `${code}-${semester}`,
            semester,
            weekday: 1,
            hour: 1,
            course: code,
            display_label: `${code} - TEST`,
            subject: code.replace(/[0-9]/gu, ''),
        })
        const ctx = {
            ...methods,
            restrictCourseChoiceBySelection: true,
            courseChoiceRestrictionMode: 'planned_additional',
            selection: {
                semester: 1,
                religion: 'ETH',
                language: 'LPT',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            configuredSchoolHours: [],
            weekdays: [{ label: 'Mo', value: 1 }],
            configuredCourseGroups: [
                ...['D1', 'E1', 'ETH1', 'GW1', 'INF1', 'LPT', 'M1', 'GS1', 'L1', 'BU1', 'CH1', 'PH1', 'PP1', 'ÖKO1']
                    .map(code => courseGroup(code, 1)),
                ...['D2', 'E2', 'ETH2', 'GW2', 'INF2'].map(code => courseGroup(code, 2)),
                { ...courseGroup('M', 2), key: 'M2-2', module_code: 'M2', display_label: 'M2 - TEST' },
                { ...courseGroup('M', 1), key: 'M3-1', display_label: 'M3 - TEST' },
                { ...courseGroup('M', 2), key: 'M4-2', display_label: 'M4 - TEST' },
                courseGroup('Rk1', 1),
                courseGroup('BIO1', 1),
                courseGroup('ME1', 1),
            ],
            transferredStudentContext: {
                courses: studentCourses,
            },
        }

        Object.defineProperty(ctx, 'courseChoiceCourseGroups', {
            get() {
                return computed.courseChoiceCourseGroups.call(this)
            },
        })
        Object.defineProperty(ctx, 'restrictedStudentCourseCodes', {
            get() {
                return computed.restrictedStudentCourseCodes.call(this)
            },
        })

        expect(new Set(methods.buildSemesterCourseMenus.call(ctx, 1).map((courseMenu: Record<string, string>) => courseMenu.label)))
            .toEqual(new Set(['BU', 'CH', 'D', 'E', 'ETH', 'GS', 'GW', 'INF', 'L', 'LPT', 'M', 'PH', 'PP', 'ÖKO']))
        expect(new Set(methods.buildSemesterCourseMenus.call(ctx, 2).map((courseMenu: Record<string, string>) => courseMenu.label)))
            .toEqual(new Set(['D', 'E', 'ETH', 'GW', 'INF', 'M']))
    })

    it('restricts course choice menus by selected options when no student is selected', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            ...methods,
            restrictCourseChoiceBySelection: true,
            courseChoiceRestrictionMode: 'planned',
            selection: {
                semester: 6,
                religion: 'ETH',
                language: 'S',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            configuredSchoolHours: [],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            subjectRows: [
                {
                    id: 1,
                    is_active: true,
                    semester: 6,
                    json_subject: 'R/ET',
                    json_code: 'R/ET2',
                    name: 'Ethik / Religion',
                    branch: 'common',
                    hours_per_week: 2,
                },
                {
                    id: 2,
                    is_active: true,
                    semester: 6,
                    json_subject: 'L/F/S',
                    json_code: 'S5',
                    name: 'Sprache',
                    branch: 'common',
                    hours_per_week: 4,
                },
                {
                    id: 3,
                    is_active: true,
                    semester: 6,
                    json_subject: 'D',
                    json_code: 'D6',
                    name: 'Deutsch',
                    branch: 'common',
                    hours_per_week: 3,
                },
                {
                    id: 4,
                    is_active: true,
                    semester: 6,
                    json_subject: 'INF',
                    json_code: 'INF2',
                    name: 'Informatik',
                    branch: 'wirtschaftskundlich',
                    hours_per_week: 3,
                },
                {
                    id: 5,
                    is_active: true,
                    semester: 6,
                    json_subject: 'BE',
                    json_code: 'BE1',
                    name: 'Bildnerische Erziehung',
                    branch: 'gymnasial',
                    hours_per_week: 2,
                },
                {
                    id: 6,
                    is_active: true,
                    semester: 6,
                    json_subject: 'F',
                    json_code: 'F5',
                    name: 'Französisch',
                    branch: 'common',
                    hours_per_week: 4,
                },
            ],
            configuredCourseGroups: [
                { key: 'eth-2', semester: 2, weekday: 1, hour: 1, course: 'ETH2', display_label: 'ETH2 - 2AF - PLOEC', subject: 'ETH' },
                { key: 'spa-5', semester: 2, weekday: 1, hour: 2, course: 'SPA5', display_label: 'SPA5 - KOR', subject: 'SPA' },
                { key: 'd-6', semester: 2, weekday: 2, hour: 1, course: 'D6', display_label: 'D6 - ABC', subject: 'D' },
                { key: 'inf-2', semester: 2, weekday: 2, hour: 2, course: 'INF2', display_label: 'INF2 - ABC', subject: 'INF' },
                { key: 'be-1', semester: 2, weekday: 3, hour: 1, course: 'BE1', display_label: 'BE1 - ABC', subject: 'BE' },
                { key: 'f-5', semester: 2, weekday: 3, hour: 2, course: 'F5', display_label: 'F5 - ABC', subject: 'F' },
                { key: 'm-6', semester: 2, weekday: 4, hour: 1, course: 'M6', display_label: 'M6 - ABC', subject: 'M' },
            ],
            transferredStudentContext: null,
        }

        Object.defineProperty(ctx, 'courseChoiceCourseGroups', {
            get() {
                return computed.courseChoiceCourseGroups.call(this)
            },
        })
        Object.defineProperty(ctx, 'selectionCourseChoiceCodes', {
            get() {
                return computed.selectionCourseChoiceCodes.call(this)
            },
        })
        Object.defineProperty(ctx, 'restrictedStudentCourseCodes', {
            get() {
                return computed.restrictedStudentCourseCodes.call(this)
            },
        })

        const selectionCourseChoiceCodes = [...computed.selectionCourseChoiceCodes.call(ctx)]

        expect(selectionCourseChoiceCodes).toEqual(expect.arrayContaining(['D6', 'ETH2', 'INF2', 'S5', 'SPA5']))
        expect(selectionCourseChoiceCodes).not.toContain('BE1')
        expect(selectionCourseChoiceCodes).not.toContain('F5')
        expect(methods.buildSemesterCourseMenus.call(ctx, 2).map((courseMenu: Record<string, string>) => courseMenu.label))
            .toEqual(['D', 'ETH', 'INF', 'SPA'])
    })

    it('falls back to available timetable semesters when the selected student semester has no imported groups', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            ...methods,
            restrictCourseChoiceBySelection: true,
            courseChoiceRestrictionMode: 'planned',
            selection: {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            configuredSchoolHours: [],
            weekdays: [
                { label: 'Mo', value: 1 },
                { label: 'Di', value: 2 },
                { label: 'Mi', value: 3 },
                { label: 'Do', value: 4 },
                { label: 'Fr', value: 5 },
                { label: 'Sa', value: 6 },
            ],
            subjectRows: [
                { id: 1, is_active: true, semester: 1, json_subject: 'D', json_code: 'D1', name: 'Deutsch 1', branch: 'common', hours_per_week: 3 },
                { id: 2, is_active: true, semester: 1, json_subject: 'R/ET', json_code: 'R/ET1', name: 'Religion/Ethik 1', branch: 'common', hours_per_week: 2 },
                { id: 3, is_active: true, semester: 1, json_subject: 'LPT', json_code: 'LPT', name: 'Lern- und Präsentationstechnik', branch: 'common', hours_per_week: 2 },
                { id: 4, is_active: true, semester: 1, json_subject: 'M', json_code: 'M1', name: 'Mathematik 1', branch: 'common', hours_per_week: 4 },
            ],
            configuredCourseGroups: [
                { key: 'd-1', semester: 2, weekday: 1, hour: 1, course: 'D', display_label: 'D1 - 1C - GOS', subject: 'D' },
                { key: 'eth-1', semester: 2, weekday: 1, hour: 2, course: 'ETH', display_label: 'ETH1 - 1CK - PLOE', subject: 'ETH' },
                { key: 'lpt', semester: 2, weekday: 2, hour: 1, course: 'LET', title: 'LPT', display_label: 'LPT - 1A - APP', subject: 'LET' },
                { key: 'm-1', semester: 2, weekday: 2, hour: 2, course: 'M', module_code: 'M1', display_label: 'M1 - 1R - FUCH', subject: 'M' },
                { key: 'd-2', semester: 2, weekday: 3, hour: 1, course: 'D', display_label: 'D2 - 2A - ENNS', subject: 'D' },
            ],
            transferredStudentContext: null,
        }

        Object.defineProperty(ctx, 'courseChoiceCourseGroups', {
            get() {
                return computed.courseChoiceCourseGroups.call(this)
            },
        })
        Object.defineProperty(ctx, 'selectionCourseChoiceCodes', {
            get() {
                return computed.selectionCourseChoiceCodes.call(this)
            },
        })
        Object.defineProperty(ctx, 'restrictedStudentCourseCodes', {
            get() {
                return computed.restrictedStudentCourseCodes.call(this)
            },
        })

        expect(methods.shouldRestrictCourseChoiceByTimetableSemester.call(ctx)).toBe(false)
        expect(methods.buildSemesterCourseMenus.call(ctx, 1)).toEqual([])
        expect(methods.buildSemesterCourseMenus.call(ctx, 2).map((courseMenu: Record<string, string>) => courseMenu.label))
            .toEqual(['D', 'ETH', 'LPT', 'M'])
    })

    it('edits and persists the overview selection summary', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            selectionDialogOpen: false,
            selection: methods.defaultSelection(),
            selectionDraft: methods.defaultSelection(),
            semesterOptions: computed.semesterOptions.call({}),
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            transferredStudentContext: null,
            defaultSelection: methods.defaultSelection,
            normalizedSelection: methods.normalizedSelection,
            selectedOptionTitle: methods.selectedOptionTitle,
            refreshTransferredStudentCourseHistory: vi.fn(),
            persistTimetableState: vi.fn(),
            runTimetableUpdate(action: () => void) {
                action()
            },
        }

        methods.openSelectionDialog.call(ctx)
        ctx.selectionDraft = {
            semester: 6,
            religion: 'ETH',
            language: 'S',
            branch: 'wirtschaftskundlich',
            artsSubject: 'ME',
        }
        methods.updateSelection.call(ctx)

        expect(ctx.selectionDialogOpen).toBe(false)
        expect(ctx.selection).toEqual({
            semester: 6,
            religion: 'ETH',
            language: 'S',
            branch: 'wirtschaftskundlich',
            artsSubject: 'ME',
        })
        expect(ctx.persistTimetableState).toHaveBeenCalled()
        expect(ctx.refreshTransferredStudentCourseHistory).toHaveBeenCalled()
        expect(computed.selectedSummary.call(ctx).map((item: Record<string, string>) => item.value)).toEqual([
            'Semester 6',
            'ETH - Ethik',
            'S - Spanisch',
            'Wirtschaftskundlicher Zweig',
            'ME - Musikerziehung',
        ])
    })

    it('summarizes enabled Wizzard evaluation criteria like the robot settings', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const criteria = methods.enabledEvaluationCriteriaFromSettings.call(methods, [
            {
                key: 'full-green',
                label: 'Voller grüner Stundenplan',
                enabled: true,
                priority: 2,
                option: null,
                options: [],
            },
            {
                key: 'compact-days',
                label: 'Kompakte Tage',
                enabled: true,
                priority: 1,
                option: 'strict',
                options: [
                    { value: 'strict', label: 'streng' },
                ],
            },
            {
                key: 'disabled',
                label: 'Inaktiv',
                enabled: false,
                priority: 3,
                option: null,
                options: [],
            },
        ])

        expect(criteria).toHaveLength(2)
        expect(computed.activeEvaluationCriteria.call({ evaluationCriteria: criteria })).toEqual([
            { key: 'full-green', label: 'Voller grüner Stundenplan', optionLabel: null },
            { key: 'compact-days', label: 'Kompakte Tage', optionLabel: 'streng' },
        ])
    })

    it('resets the Wizzard panel when evaluation settings change', () => {
        const methods = (Overview as any).methods
        const showCourseActionAfterCourseInteraction = vi.fn()
        const ctx = {
            ...methods,
            evaluationCriteria: [
                { key: 'free_days', label: 'Anzahl freie Tage', enabled: true, priority: 1 },
            ],
            $refs: {
                wizardCourseCards: {
                    resetStudentCourseSelection: vi.fn(),
                    clearGeneratedTimetables: vi.fn(),
                    showCourseActionAfterCourseInteraction,
                },
            },
            activeCourseGroupFilterKeys: ['INF1'],
            removeSavedRobotTimetableState: vi.fn(),
        }

        methods.onSettingsChanged.call(ctx, [
            { key: 'few_gaps', label: 'Wenig Lücken', enabled: true, priority: 1 },
            { key: 'free_days', label: 'Anzahl freie Tage', enabled: false, priority: 2 },
        ])

        expect(ctx.evaluationCriteria).toEqual([
            {
                key: 'few_gaps',
                label: 'Wenig Lücken',
                enabled: true,
                priority: 1,
                option: null,
                options: [],
            },
        ])
        expect(showCourseActionAfterCourseInteraction).toHaveBeenCalled()
        expect(ctx.activeCourseGroupFilterKeys).toEqual([])
        expect(ctx.removeSavedRobotTimetableState).toHaveBeenCalled()
    })

    it('keeps the Wizzard panel when evaluation settings emit unchanged criteria', () => {
        const methods = (Overview as any).methods
        const ctx = {
            ...methods,
            evaluationCriteria: [
                { key: 'free_days', label: 'Anzahl freie Tage', enabled: true, priority: 1 },
            ],
            resetWizardPanel: vi.fn(),
        }

        methods.onSettingsChanged.call(ctx, [
            { key: 'free_days', label: 'Anzahl freie Tage', enabled: true, priority: 1 },
            { key: 'few_gaps', label: 'Wenig Lücken', enabled: false, priority: 2 },
        ])

        expect(ctx.resetWizardPanel).not.toHaveBeenCalled()
    })

    it('switches the embedded Wizzard and manual course panels on the overview page', async () => {
        const methods = (Overview as any).methods
        const loadFullGreenTimetableCount = vi.fn()
        const ctx = {
            wizardPanelOpen: false,
            wizardPanelMounted: false,
            manualPanelOpen: false,
            manualPanelSource: null,
            wizardTimetableCreating: false,
            closeWizardPanel: methods.closeWizardPanel,
            openManualPanel: methods.openManualPanel,
            closeManualPanel: methods.closeManualPanel,
            $refs: {
                wizardCourseCards: {
                    loadFullGreenTimetableCount,
                },
            },
        }

        methods.openWizardPanel.call(ctx)

        expect(ctx.wizardPanelOpen).toBe(true)
        expect(ctx.wizardPanelMounted).toBe(true)
        expect(ctx.manualPanelOpen).toBe(false)
        expect(ctx.manualPanelSource).toBeNull()

        await methods.createWizardTimetable.call(ctx)

        expect(loadFullGreenTimetableCount).toHaveBeenCalled()

        methods.toggleManualPanel.call(ctx)

        expect(ctx.wizardPanelOpen).toBe(false)
        expect(ctx.manualPanelOpen).toBe(true)
        expect(ctx.manualPanelSource).toBe('direct')

        methods.openWizardPanel.call(ctx)
        methods.closeWizardPanel.call(ctx)

        expect(ctx.wizardPanelOpen).toBe(false)
        expect(ctx.wizardPanelMounted).toBe(true)
        expect(ctx.manualPanelSource).toBeNull()

        methods.toggleManualPanel.call(ctx)

        expect(ctx.manualPanelOpen).toBe(true)
        expect(ctx.manualPanelSource).toBe('direct')

        methods.closeActiveTimetablePanel.call(ctx)

        expect(ctx.manualPanelOpen).toBe(false)
        expect(ctx.manualPanelSource).toBeNull()
    })

    it('syncs overview timetable panel clicks with route URLs', () => {
        const methods = (Overview as any).methods
        const pushedPaths: string[] = []
        const ctx = {
            wizardPanelOpen: false,
            wizardPanelMounted: false,
            manualPanelOpen: false,
            manualPanelSource: null,
            normalizedTimetableOverviewRouteMode: methods.normalizedTimetableOverviewRouteMode,
            timetableOverviewModePath: methods.timetableOverviewModePath,
            navigateToTimetableOverviewMode: methods.navigateToTimetableOverviewMode,
            openWizardPanel: methods.openWizardPanel,
            openManualPanel: methods.openManualPanel,
            closeManualPanel: methods.closeManualPanel,
            $route: {
                path: '/admin/students-timetables',
                params: {},
            },
            $router: {
                push({ path }: { path: string }) {
                    pushedPaths.push(path)
                    ctx.$route.path = path
                },
            },
        }

        methods.openWizardPanel.call(ctx)
        methods.openManualPanel.call(ctx)
        methods.closeManualPanel.call(ctx)

        expect(pushedPaths).toEqual([
            '/admin/students-timetables/timetable/overview/automatic',
            '/admin/students-timetables/timetable/overview/manual',
            '/admin/students-timetables',
        ])
    })

    it('restores overview timetable panel state from route URLs', () => {
        const methods = (Overview as any).methods
        const ctx = {
            wizardPanelOpen: false,
            wizardPanelMounted: false,
            manualPanelOpen: false,
            manualPanelSource: null,
            activeCourseGroupFilterKeys: [],
            selectedRecurrenceWeeks: {
                1: 'all_dates',
                2: 'all_dates',
            },
            expandedRecurrenceWeeks: {
                1: false,
                2: false,
            },
            showExtraDatesInSelectedWeeks: {
                1: false,
                2: false,
            },
            restrictCourseChoiceBySelection: false,
            selection: {
                semester: 2,
                religion: 'RK',
                language: 'F',
                branch: 'gymnasial',
                artsSubject: 'BE',
            },
            transferredStudentContext: {
                student: {
                    studentCode: 'S1',
                    label: 'Student 1',
                    semesterLabel: 'Semester 2',
                },
                courses: {},
            },
            normalizedTimetableOverviewRouteMode: methods.normalizedTimetableOverviewRouteMode,
            applyTimetableOverviewLandingState: methods.applyTimetableOverviewLandingState,
            applyTimetableState: methods.applyTimetableState,
            currentTimetableState: methods.currentTimetableState,
            defaultTimetableState: methods.defaultTimetableState,
            defaultSelection: methods.defaultSelection,
            normalizedSelection: methods.normalizedSelection,
            normalizedCourseChoiceRestrictionMode: methods.normalizedCourseChoiceRestrictionMode,
            normalizedTransferredStudentContext: methods.normalizedTransferredStudentContext,
            normalizedTransferredStudentCourses: methods.normalizedTransferredStudentCourses,
            savedRobotTimetableStateAvailable: () => true,
            openWizardPanel: methods.openWizardPanel,
            openManualPanel: methods.openManualPanel,
        }

        methods.applyTimetableOverviewModeFromRoute.call(ctx, 'automatic')

        expect(ctx.wizardPanelOpen).toBe(true)
        expect(ctx.wizardPanelMounted).toBe(true)
        expect(ctx.manualPanelOpen).toBe(false)

        methods.applyTimetableOverviewModeFromRoute.call(ctx, 'manual')

        expect(ctx.wizardPanelOpen).toBe(false)
        expect(ctx.manualPanelOpen).toBe(true)
        expect(ctx.manualPanelSource).toBe('direct')

        methods.applyTimetableOverviewModeFromRoute.call(ctx, 'adopted')

        expect(ctx.wizardPanelOpen).toBe(false)
        expect(ctx.wizardPanelMounted).toBe(true)
        expect(ctx.manualPanelOpen).toBe(true)
        expect(ctx.manualPanelSource).toBe('wizard')

        ctx.activeCourseGroupFilterKeys = ['old-course']
        methods.applyTimetableOverviewModeFromRoute.call(ctx, undefined)

        expect(ctx.activeCourseGroupFilterKeys).toEqual([])
        expect(ctx.selection).toEqual({
            semester: 2,
            religion: 'RK',
            language: 'F',
            branch: 'gymnasial',
            artsSubject: 'BE',
        })
        expect(ctx.transferredStudentContext?.student?.studentCode).toBe('S1')
        expect(ctx.wizardPanelOpen).toBe(false)
        expect(ctx.wizardPanelMounted).toBe(false)
        expect(ctx.manualPanelOpen).toBe(false)
        expect(ctx.manualPanelSource).toBeNull()
    })

    it('shows an overtaken Wizzard timetable as the manual overview timetable', () => {
        const methods = (Overview as any).methods
        const saveLastTimetableState = vi.fn()
        const ctx = {
            activeCourseGroupFilterKeys: [],
            selectedRecurrenceWeeks: {
                1: 'all_dates',
                2: 'all_dates',
            },
            expandedRecurrenceWeeks: {
                1: false,
                2: false,
            },
            showExtraDatesInSelectedWeeks: {
                1: false,
                2: false,
            },
            wizardPanelOpen: true,
            wizardPanelMounted: true,
            manualPanelOpen: false,
            manualPanelSource: null,
            restrictCourseChoiceBySelection: false,
            selection: methods.defaultSelection(),
            selectionDraft: methods.defaultSelection(),
            transferredStudentContext: null,
            defaultTimetableState: methods.defaultTimetableState,
            defaultSelection: methods.defaultSelection,
            normalizedSelection: methods.normalizedSelection,
            normalizedCourseChoiceRestrictionMode: methods.normalizedCourseChoiceRestrictionMode,
            normalizedTransferredStudentContext: methods.normalizedTransferredStudentContext,
            normalizedTransferredStudentCourses: methods.normalizedTransferredStudentCourses,
            applyTimetableState: methods.applyTimetableState,
            saveLastTimetableState,
            runTimetableUpdate(action: () => void) {
                action()
            },
        }

        methods.showOvertakenManualTimetable.call(ctx, {
            activeCourseGroupFilterKeys: ['bio-1', 'bio-2'],
        })

        expect(ctx.activeCourseGroupFilterKeys).toEqual(['bio-1', 'bio-2'])
        expect(ctx.manualPanelOpen).toBe(true)
        expect(ctx.manualPanelSource).toBe('wizard')
        expect(ctx.wizardPanelOpen).toBe(false)
        expect(ctx.wizardPanelMounted).toBe(true)
        expect(saveLastTimetableState).toHaveBeenCalled()
    })

    it('asks the embedded Wizzard to require additional courses when extending timetables', async () => {
        const methods = (Overview as any).methods
        const createTimetables = vi.fn()
        const ctx = {
            wizardTimetableCreating: false,
            $refs: {
                wizardCourseCards: {
                    createTimetables,
                },
            },
        }

        await methods.createWizardTimetable.call(ctx, true)

        expect(createTimetables).toHaveBeenCalledWith({
            requireAdditionalCourses: true,
        })
    })

    it('keeps the overview create button loading while the embedded Wizzard creates timetables', async () => {
        const methods = (Overview as any).methods
        let finishCreation = () => {}
        const createTimetables = vi.fn(() => new Promise(resolve => {
            finishCreation = resolve
        }))
        const ctx = {
            wizardTimetableCreating: false,
            $refs: {
                wizardCourseCards: {
                    createTimetables,
                },
            },
        }

        const createPromise = methods.createWizardTimetable.call(ctx)

        expect(ctx.wizardTimetableCreating).toBe(true)
        expect(createTimetables).toHaveBeenCalled()

        finishCreation()
        await createPromise

        expect(ctx.wizardTimetableCreating).toBe(false)
    })

    it('shows all transferred student course rows even when some are empty', () => {
        const computed = (Overview as any).computed
        const ctx = {
            transferredStudentContext: {
                student: {
                    label: '4Q · GRASSL Tobias · Semester 6',
                },
                courses: {
                    completed: [{ key: 'ETH1', label: 'ETH1', meta: '1' }],
                    missing: [],
                    planned: [{ key: 'S5', label: 'S5', meta: '4 Std.' }],
                    additional: [],
                },
            },
        }
        Object.defineProperty(ctx, 'transferredStudentCourseSections', {
            get() {
                return computed.transferredStudentCourseSections.call(this)
            },
        })

        expect(computed.visibleTransferredStudentCourseSections.call(ctx).map((section: Record<string, string>) => section.key))
            .toEqual(['completed', 'missing', 'planned', 'additional'])
        expect(computed.visibleTransferredStudentCourseSections.call(ctx).map((section: Record<string, string>) => section.icon))
            .toEqual(['mdi-check-circle-outline', 'mdi-alert-circle-outline', 'mdi-format-list-checks', 'mdi-plus-circle-outline'])
        expect(computed.visibleTransferredStudentCourseSections.call(ctx).map((section: Record<string, string>) => section.color))
            .toEqual(['success', 'warning', 'primary', 'info'])
    })

    it('shows imported religion from robot student data when restored student context is stale', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            normalizedStudentCode: methods.normalizedStudentCode,
            studentReligionMeta: methods.studentReligionMeta,
            transferredStudentContext: {
                student: {
                    studentCode: '100',
                    label: '4Q · GRASSL Tobias · Semester 6',
                    semesterLabel: 'Semester 6',
                },
                courses: {
                    completed: [],
                    missing: [],
                    planned: [],
                    additional: [],
                },
            },
            robotStudents: [
                { student_code: '100', religion: 'Rk' },
            ],
        }
        Object.defineProperty(ctx, 'transferredStudentReligion', {
            get() {
                return computed.transferredStudentReligion.call(this)
            },
        })

        expect(computed.transferredStudentReligion.call(ctx)).toBe('Rk')
        expect(computed.transferredStudentReligionMeta.call(ctx)).toBe('Religion: Rk')
    })

    it('does not render student course cards without a selected student', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/overview/Overview.vue',
            'utf8',
        )

        expect(componentSource).toContain('<div v-if="transferredStudentContext" class="transferred-student-course-overview">')
    })

    it('still derives possible selected-option courses for filtering without a selected student', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            ...methods,
            transferredStudentContext: null,
            selection: {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            subjectRows: [
                {
                    id: 1,
                    is_active: true,
                    semester: 1,
                    json_subject: 'D',
                    json_code: 'D1',
                    name: 'Deutsch 1',
                    branch: 'common',
                    hours_per_week: 3,
                },
                {
                    id: 2,
                    is_active: true,
                    semester: 2,
                    json_subject: 'D',
                    json_code: 'D2',
                    name: 'Deutsch 2',
                    branch: 'common',
                    hours_per_week: 3,
                },
                {
                    id: 3,
                    is_active: true,
                    semester: 2,
                    json_subject: 'GS',
                    json_code: 'GS1',
                    name: 'Geschichte 1',
                    branch: 'common',
                    hours_per_week: 4,
                },
            ],
        }
        Object.defineProperty(ctx, 'noStudentCourseHistory', {
            get() {
                return computed.noStudentCourseHistory.call(this)
            },
        })
        Object.defineProperty(ctx, 'transferredStudentCourseSections', {
            get() {
                return computed.transferredStudentCourseSections.call(this)
            },
        })

        const sections = computed.visibleTransferredStudentCourseSections.call(ctx)

        expect(sections.map((section: Record<string, string>) => section.key))
            .toEqual(['completed', 'missing', 'planned', 'additional'])
        expect(sections[0].items).toEqual([])
        expect(sections[1].items).toEqual([])
        expect(sections[2].items.map((course: Record<string, string>) => `${course.label} ${course.meta}`))
            .toEqual(['D1 3 Std.'])
        expect(sections[3].items.map((course: Record<string, string>) => `${course.label} ${course.meta}`))
            .toEqual(['D2 3 Std.', 'GS1 4 Std.'])
    })

    it('derives missing planned and additional rows for a selected overview student', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            ...methods,
            selection: {
                semester: 6,
                religion: 'ETH',
                language: 'S',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            subjectRows: [
                {
                    id: 1,
                    is_active: true,
                    semester: 5,
                    json_subject: 'CH',
                    json_code: 'CH2',
                    name: 'Chemie',
                    branch: 'common',
                    hours_per_week: 3,
                },
                {
                    id: 2,
                    is_active: true,
                    semester: 6,
                    json_subject: 'S',
                    json_code: 'S5',
                    name: 'Spanisch',
                    branch: 'common',
                    hours_per_week: 4,
                },
                {
                    id: 3,
                    is_active: true,
                    semester: 7,
                    json_subject: 'INF',
                    json_code: 'INF2',
                    name: 'Informatik',
                    branch: 'wirtschaftskundlich',
                    hours_per_week: 3,
                },
                {
                    id: 4,
                    is_active: true,
                    semester: 7,
                    json_subject: 'BE',
                    json_code: 'BE1',
                    name: 'Bildnerische Erziehung',
                    branch: 'gymnasial',
                    hours_per_week: 2,
                },
            ],
        }

        const history = methods.overviewStudentCourseHistory.call(ctx, [
            { subject: 'CH1', grade: 'B' },
            { subject: 'S3', grade: '3' },
            { subject: 'INF1', grade: '1' },
        ])

        expect(history.completed.map((course: Record<string, string>) => course.label)).toEqual(['CH1', 'S3', 'INF1'])
        expect(history.missing.map((course: Record<string, string>) => course.label)).toEqual(['CH2'])
        expect(history.planned.map((course: Record<string, string>) => course.label)).toEqual(['S5'])
        expect(history.additional.map((course: Record<string, string>) => course.label)).toEqual(['INF2'])
    })

    it('continues religion or ethics modules after completed alternate modules for overview students', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            ...methods,
            selection: {
                semester: 6,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            subjectRows: [
                {
                    id: 1,
                    is_active: true,
                    semester: 1,
                    json_subject: 'R/ET',
                    json_code: 'R/ET1',
                    name: 'Religion / Ethik 1',
                    branch: 'common',
                    hours_per_week: 2,
                },
                {
                    id: 2,
                    is_active: true,
                    semester: 2,
                    json_subject: 'R/ET',
                    json_code: 'R/ET2',
                    name: 'Religion / Ethik 2',
                    branch: 'common',
                    hours_per_week: 2,
                },
                {
                    id: 3,
                    is_active: true,
                    semester: 3,
                    json_subject: 'R/ET',
                    json_code: 'R/ET3',
                    name: 'Religion / Ethik 3',
                    branch: 'common',
                    hours_per_week: 2,
                },
            ],
        }
        const completedReligionCourses = [
            { subject: 'R1', grade: '2' },
            { subject: 'R2', grade: '3' },
        ]

        expect(methods.overviewStudentCourseHistory.call(ctx, completedReligionCourses).missing
            .map((course: Record<string, string>) => course.label))
            .toEqual(['ETH3'])

        ctx.selection.religion = 'Rk'

        expect(methods.overviewStudentCourseHistory.call(ctx, completedReligionCourses).missing
            .map((course: Record<string, string>) => course.label))
            .toEqual(['Rk3'])
    })

    it('infers selected overview alternatives from selected student course history', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            ...methods,
            selection: {
                semester: 6,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'BE',
            },
            selectionDraft: {
                semester: 6,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'BE',
            },
            selectedCourseMenuKey: 'old-menu',
            selectedCourseGroup: { key: 'old-group' },
            religionOptions: computed.religionOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            subjectRows: [
                {
                    semester: 3,
                    branch: 'gymnasial',
                    json_code: 'GYM3',
                    json_subject: 'GYM',
                    name: 'Gymnasial 3',
                    is_active: true,
                },
            ],
        }

        methods.applyTransferredStudentSelectionDefaultsFromCourses.call(ctx, [
            { subject: 'L1', grade: '2' },
            { subject: 'L2', grade: '3' },
            { subject: 'SPA3', grade: '3' },
            { subject: 'GYM3', grade: '2' },
            { subject: 'MU1', grade: 'B' },
            { subject: 'R1', grade: '4' },
        ])

        expect(ctx.selection).toEqual({
            semester: 6,
            religion: 'Rk',
            language: 'S',
            branch: 'gymnasial',
            artsSubject: 'ME',
        })
        expect(ctx.selectionDraft).toEqual(ctx.selection)
        expect(ctx.selectedCourseMenuKey).toBe('')
        expect(ctx.selectedCourseGroup).toBeNull()
    })

    it('focuses the student search field when editing the overview student', () => {
        const methods = (Overview as any).methods
        const focusCalls: string[] = []
        const ctx = {
            transferredStudentContext: {
                student: {
                    studentCode: '100',
                },
            },
            studentSelectionDraft: {
                studentCode: null,
            },
            studentDialogOpen: false,
            studentSearch: 'Grassl',
            normalizedStudentCode: methods.normalizedStudentCode,
            focusStudentSearchField: methods.focusStudentSearchField,
            $refs: {
                studentSearchField: {
                    focus() {
                        focusCalls.push('field')
                    },
                    $el: {
                        querySelector(selector: string) {
                            expect(selector).toBe('input')

                            return {
                                focus() {
                                    focusCalls.push('input')
                                },
                            }
                        },
                    },
                },
            },
            $nextTick(callback: () => void) {
                callback()
            },
        }

        methods.openStudentDialog.call(ctx)

        expect(ctx.studentSelectionDraft).toEqual({ studentCode: '100' })
        expect(ctx.studentSearch).toBe('')
        expect(ctx.studentDialogOpen).toBe(true)
        expect(focusCalls).toEqual(['field', 'input'])
    })

    it('selects the only matching overview student before updating on enter', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            studentSearch: 'grassl',
            studentSelectionDraft: {
                studentCode: null,
            },
            appliedStudentCode: null as string | null,
            robotStudents: [
                { student_code: '100', class: '4Q', last_name: 'GRASSL', first_name: 'Tobias' },
                { student_code: '200', class: '4Q', last_name: 'MAYR', first_name: 'Anna' },
            ],
            normalizedStudentCode: methods.normalizedStudentCode,
            selectStudentDraft: methods.selectStudentDraft,
            updateStudentSelection: methods.updateStudentSelection,
            studentOptionTitle(student: Record<string, string>) {
                return [student.class, student.last_name, student.first_name].filter(Boolean).join(' ')
            },
            applyTransferredStudentSelection(studentCode: string | null) {
                this.appliedStudentCode = studentCode
            },
        }

        Object.defineProperty(ctx, 'normalizedStudentSearch', {
            get() {
                return computed.normalizedStudentSearch.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'studentSearchReady', {
            get() {
                return computed.studentSearchReady.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'filteredStudentResults', {
            get() {
                return computed.filteredStudentResults.call(ctx)
            },
        })

        methods.submitStudentSearch.call(ctx)

        expect(ctx.studentSelectionDraft.studentCode).toBe('100')
        expect(ctx.appliedStudentCode).toBe('100')
    })

    it('clears the transferred overview student directly', () => {
        const methods = (Overview as any).methods
        const ctx = {
            transferredStudentContext: {
                student: {
                    studentCode: '100',
                },
            },
            studentSelectionDraft: {
                studentCode: '100',
            },
            selection: {
                semester: 4,
                religion: 'RK',
                language: 'F',
                branch: 'gymnasial',
                artsSubject: 'BE',
            },
            selectionDraft: {
                semester: 4,
                religion: 'RK',
                language: 'F',
                branch: 'gymnasial',
                artsSubject: 'BE',
            },
            appliedStudentCode: '100',
            defaultSelection: methods.defaultSelection,
            applyTransferredStudentSelection(studentCode: string | null) {
                this.appliedStudentCode = studentCode
                this.transferredStudentContext = null
            },
        }

        methods.clearTransferredStudentSelection.call(ctx)

        expect(ctx.studentSelectionDraft).toEqual({ studentCode: null })
        expect(ctx.selection).toEqual(methods.defaultSelection.call(ctx))
        expect(ctx.selectionDraft).toEqual(methods.defaultSelection.call(ctx))
        expect(ctx.appliedStudentCode).toBeNull()
        expect(ctx.transferredStudentContext).toBeNull()
    })

    it('shows course items in the dialog only after a course menu is selected', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods

        const ctx = {
            courseMenuDialog: false,
            selectedCourseMenuKey: '',
            visibleCourseChoiceSemesters: [
                { value: 2, label: 'Semester', dateRangeLabel: '16.02.2026 - 10.07.2026' },
            ],
            semesterCourseMenus(semester: number) {
                return semester === 2
                    ? [
                        {
                            key: 'semester-2-ETH',
                            label: 'ETH',
                            entries: [{ key: 'eth-1', label: 'ETH 1' }],
                        },
                    ]
                    : []
            },
        }

        const allCourseChoiceMenus = computed.allCourseChoiceMenus.call(ctx)

        expect(computed.selectedCourseMenu.call({
            ...ctx,
            allCourseChoiceMenus,
        })).toBeNull()

        expect(allCourseChoiceMenus).toEqual([
            {
                key: 'semester-2-ETH',
                label: 'ETH',
                entries: [{ key: 'eth-1', label: 'ETH 1' }],
                semesterValue: 2,
                semesterLabel: 'Semester',
                semesterDateRangeLabel: '16.02.2026 - 10.07.2026',
            },
        ])

        ctx.selectedCourseMenuKey = 'semester-2-ETH'
        methods.openCourseMenuDialog.call(ctx)
        expect(ctx.courseMenuDialog).toBe(true)
        expect(ctx.selectedCourseMenuKey).toBe('')

        methods.selectCourseMenu.call(ctx, allCourseChoiceMenus[0])
        expect(ctx.selectedCourseMenuKey).toBe('semester-2-ETH')
        expect(ctx.courseMenuDialog).toBe(true)
        expect(computed.selectedCourseMenu.call({
            ...ctx,
            allCourseChoiceMenus,
        })).toEqual(allCourseChoiceMenus[0])
    })

    it('keeps the open course card and selected courses when changing the course choice mode', () => {
        const methods = (Overview as any).methods
        const ctx = {
            courseChoiceRestrictionMode: 'planned',
            restrictCourseChoiceBySelection: true,
            selectedCourseMenuKey: 'semester-2-M',
            activeCourseGroupFilterKeys: ['m-1'],
            normalizedCourseChoiceRestrictionMode: methods.normalizedCourseChoiceRestrictionMode,
            persistTimetableState: vi.fn(),
        }

        methods.handleCourseChoiceRestrictionUpdate.call(ctx, 'planned_additional')

        expect(ctx.courseChoiceRestrictionMode).toBe('planned_additional')
        expect(ctx.restrictCourseChoiceBySelection).toBe(true)
        expect(ctx.selectedCourseMenuKey).toBe('semester-2-M')
        expect(ctx.activeCourseGroupFilterKeys).toEqual(['m-1'])
        expect(ctx.persistTimetableState).not.toHaveBeenCalled()
    })

    it('keeps selected course chips independent from the course choice mode filter', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx = {
            ...methods,
            semesters: [
                { value: 1, label: 'Semester 1', dateRangeLabel: '' },
                { value: 2, label: 'Semester 2', dateRangeLabel: '' },
            ],
            activeCourseGroupFilterKeys: ['eth-1', 'm-1'],
            activeCourseGroupFilterKeySet: new Set(['eth-1', 'm-1']),
            configuredSchoolHours: [],
            weekdays: [],
            configuredCourseGroups: [
                { key: 'eth-1', semester: 2, weekday: 1, hour: 1, course: 'ETH1', display_label: 'ETH1 - 1CK - PLÖ', subject: 'ETH' },
                { key: 'm-1', semester: 2, weekday: 1, hour: 2, course: 'M', module_code: 'M1', display_label: 'M1 - 1C - MAY', subject: 'M' },
            ],
            courseChoiceCourseGroups: [
                { key: 'eth-1', semester: 2, weekday: 1, hour: 1, course: 'ETH1', display_label: 'ETH1 - 1CK - PLÖ', subject: 'ETH' },
            ],
        }

        Object.defineProperty(ctx, 'selectedCourseMenuEntriesBySemester', {
            get() {
                return computed.selectedCourseMenuEntriesBySemester.call(this)
            },
        })

        expect(computed.selectedCourseCount.call(ctx)).toBe(2)
        expect(computed.selectedCourseFilterChipsAll.call(ctx).map((filterChip: Record<string, string>) => filterChip.label))
            .toEqual(['ETH1 - 1CK - PLÖ', 'M1 - 1C - MAY'])
        expect(methods.buildSemesterCourseMenus.call(ctx, 2).map((courseMenu: Record<string, string>) => courseMenu.label))
            .toEqual(['ETH'])
    })

    it('updates course choices immediately and precomputes selected menu entry state', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const entry = {
            key: 'semester-2-M-M1',
            label: 'M1 - 1C - MAY',
            scheduleLabel: 'Mi',
            courseGroupKeys: ['m-1'],
        }
        const ctx = {
            selectedCourseMenu: {
                semesterValue: 2,
                entries: [entry],
            },
            courseMenuEntryHasBlockingOverlap: vi.fn(() => false),
            courseMenuEntryHasRelatedOverlap: vi.fn(() => false),
            isCourseMenuEntryFilterActive: vi.fn(() => true),
            toggleCourseMenuEntryFilter: vi.fn(),
            runTimetableUpdate: vi.fn(),
        }

        const entryOptions = computed.selectedCourseMenuEntryOptions.call(ctx)

        expect(entryOptions).toEqual([{
            ...entry,
            entry,
            hasBlockingOverlap: false,
            hasRelatedOverlap: false,
            isActive: true,
            color: 'success',
        }])
        expect(ctx.courseMenuEntryHasRelatedOverlap).toHaveBeenCalledWith(entry, 2, { hasBlockingOverlap: false })

        methods.handleCourseMenuEntryFilterClick.call(ctx, entry)

        expect(ctx.toggleCourseMenuEntryFilter).toHaveBeenCalledWith(entry)
        expect(ctx.runTimetableUpdate).not.toHaveBeenCalled()
    })

    it('marks course menu chips as active when one of their entries is selected', () => {
        const methods = (Overview as any).methods
        const selectedEntry = { key: 'eth-1', courseGroupKeys: ['eth-1'] }
        const unselectedEntry = { key: 'eth-2', courseGroupKeys: ['eth-2'] }
        const ctx = {
            activeCourseGroupFilterKeys: ['eth-1'],
            activeCourseGroupFilterKeySet: new Set(['eth-1']),
            isCourseMenuEntryFilterActive: methods.isCourseMenuEntryFilterActive,
            courseMenuEntryKeys: methods.courseMenuEntryKeys,
        }

        expect(methods.courseMenuHasActiveSelection.call(ctx, {
            entries: [selectedEntry, unselectedEntry],
        })).toBe(true)
        expect(methods.courseMenuHasActiveSelection.call(ctx, {
            entries: [unselectedEntry],
        })).toBe(false)
    })

    it('shows only semesters with selected timetable courses', () => {
        const computed = (Overview as any).computed

        const ctx = {
            semesters: [
                { value: 1, label: 'Semester' },
                { value: 2, label: 'Semester' },
            ],
            selectedCourseMenuEntries(semester: number) {
                return semester === 2 ? [{ key: 'kg1' }] : []
            },
        }

        expect(computed.visibleTimetableSemesters.call(ctx)).toEqual([
            { value: 2, label: 'Semester' },
        ])
    })
})
