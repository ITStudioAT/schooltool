import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import Overview from '@/pages/admin/studentsTimetables/overview/Overview.vue'

describe('Students timetable overview', () => {
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

        vi.advanceTimersByTime(150)

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
            selectedCourseMenuEntries: methods.selectedCourseMenuEntries,
            courseMenuEntriesOverlap: methods.courseMenuEntriesOverlap,
            courseGroupsOverlap: methods.courseGroupsOverlap,
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
            selectedCourseFilterChips: methods.selectedCourseFilterChips,
            selectedCourseMenuEntries: methods.selectedCourseMenuEntries,
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
            courseGroupHasOverlap: methods.courseGroupHasOverlap,
            courseGroupHasRelatedOverlap: methods.courseGroupHasRelatedOverlap,
            courseGroupRelatedOverlapMarker: methods.courseGroupRelatedOverlapMarker,
            courseMenuEntriesOverlap: methods.courseMenuEntriesOverlap,
            courseGroupsOverlap: methods.courseGroupsOverlap,
        }

        expect(methods.selectedCourseFilterChips.call(ctx, 1).map((filterChip: Record<string, boolean>) => filterChip.hasOverlap))
            .toEqual([true, true])
        expect(methods.courseGroupHasOverlap.call(ctx, mathCourseGroup)).toBe(true)
        expect(methods.courseGroupHasRelatedOverlap.call(ctx, mathCourseGroup)).toBe(false)
        expect(methods.courseGroupRelatedOverlapMarker.call(ctx, mathCourseGroup)).toBe('')
        expect(methods.courseGroupHasOverlap.call(ctx, mathRelatedCourseGroup)).toBe(false)
        expect(methods.courseGroupHasRelatedOverlap.call(ctx, mathRelatedCourseGroup)).toBe(true)
        expect(methods.courseGroupRelatedOverlapMarker.call(ctx, mathRelatedCourseGroup)).toBe('⚠ Mitbetroffen')
        expect(methods.courseGroupHasOverlap.call(ctx, bioCourseGroup)).toBe(true)
        expect(methods.courseGroupHasRelatedOverlap.call(ctx, bioCourseGroup)).toBe(false)
        expect(methods.courseGroupRelatedOverlapMarker.call(ctx, bioCourseGroup)).toBe('')
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

    it('warns when Saturday courses are hidden by the Saturday switch', () => {
        const computed = (Overview as any).computed
        const ctx = {
            showSaturday: false,
            activeCourseGroupFilterKeys: ['saturday-course'],
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

        expect(computed.hasHiddenSaturdayCourses.call(ctx)).toBe(true)

        ctx.showSaturday = true

        expect(computed.hasHiddenSaturdayCourses.call(ctx)).toBe(false)
    })

    it('does not warn for unselected Saturday courses', () => {
        const computed = (Overview as any).computed
        const ctx = {
            showSaturday: false,
            activeCourseGroupFilterKeys: [],
            configuredCourseGroups: [
                {
                    key: 'saturday-course',
                    weekday: 6,
                },
            ],
        }

        expect(computed.hasHiddenSaturdayCourses.call(ctx)).toBe(false)
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
            showSaturday: false,
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
            timetableSelectorOptions: methods.timetableSelectorOptions,
            allDatesOption: methods.allDatesOption,
            selectedRecurrenceWeek: methods.selectedRecurrenceWeek,
            selectedTimetableOptionValue: methods.selectedTimetableOptionValue,
            setSelectedTimetableOptionValue: methods.setSelectedTimetableOptionValue,
            setSelectedRecurrenceWeek: methods.setSelectedRecurrenceWeek,
            areRecurrenceWeeksExpanded: methods.areRecurrenceWeeksExpanded,
            toggleRecurrenceWeeks: methods.toggleRecurrenceWeeks,
            visibleTimetableWeeks: methods.visibleTimetableWeeks,
            extraDatesOptions: methods.extraDatesOptions,
            shouldShowExtraDatesNotice: methods.shouldShowExtraDatesNotice,
            showExtraDatesInSelectedWeek: methods.showExtraDatesInSelectedWeek,
            setShowExtraDatesInSelectedWeek: methods.setShowExtraDatesInSelectedWeek,
            shouldIncludeExtraDatesInRegularWeek: methods.shouldIncludeExtraDatesInRegularWeek,
            courseGroupHasRegularRecurrence: methods.courseGroupHasRegularRecurrence,
            courseGroupHasExtraDateWeek: methods.courseGroupHasExtraDateWeek,
            courseGroupRecurrenceWeek: methods.courseGroupRecurrenceWeek,
            semesterStartDate: methods.semesterStartDate,
            selectedCourseMenuEntries: methods.selectedCourseMenuEntries,
            semesterCourseMenus: methods.semesterCourseMenus,
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
            .toEqual(['Alle Termine', 'Woche 1', 'Woche 2', 'Woche 3', 'Woche 4', 'Zusatzwochen'])
        expect(methods.selectedTimetableOptionValue.call(ctx, 1)).toBe('all_dates')
        expect(methods.visibleTimetableWeeks.call(ctx, 1).map((option: Record<string, string>) => option.label))
            .toEqual(['Alle Termine'])
        expect(methods.courseGroupsForCell.call(ctx, 1, 2, 2)).toEqual([weekOneCourseGroup, weekTwoCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 4, 4)).toEqual([weeklyCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 3)).toEqual([])
        expect(methods.shouldShowExtraDatesNotice.call(ctx, 1, methods.visibleTimetableWeeks.call(ctx, 1)[0])).toBe(true)

        methods.setShowExtraDatesInSelectedWeek.call(ctx, 1, true)

        expect(methods.showExtraDatesInSelectedWeek.call(ctx, 1)).toBe(true)
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 3)).toEqual([blockCourseGroup])
        methods.setShowExtraDatesInSelectedWeek.call(ctx, 1, false)

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
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 3)).toEqual([])
        expect(methods.shouldShowExtraDatesNotice.call(ctx, 1, methods.visibleTimetableWeeks.call(ctx, 1)[0])).toBe(true)

        methods.setShowExtraDatesInSelectedWeek.call(ctx, 1, true)

        expect(methods.showExtraDatesInSelectedWeek.call(ctx, 1)).toBe(true)
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 3)).toEqual([blockCourseGroup])

        methods.setSelectedTimetableOptionValue.call(ctx, 1, 'extra_dates')
        methods.toggleRecurrenceWeeks.call(ctx, 1)

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
            showExtraDatesInSelectedWeek: methods.showExtraDatesInSelectedWeek,
            shouldIncludeExtraDatesInRegularWeek: methods.shouldIncludeExtraDatesInRegularWeek,
            courseGroupHasRegularRecurrence: methods.courseGroupHasRegularRecurrence,
            courseGroupHasExtraDateWeek: methods.courseGroupHasExtraDateWeek,
            courseGroupRecurrenceWeek: methods.courseGroupRecurrenceWeek,
            semesterStartDate: () => new Date('2026-09-07T00:00:00'),
            normalizeDate: methods.normalizeDate,
        }

        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 11)).toEqual([recurringBlockCourseGroup])
        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 11, ctx.extraDatesOptions()[0])).toEqual([extraDateCourseGroup])

        ctx.showExtraDatesInSelectedWeeks[1] = true

        expect(methods.courseGroupsForCell.call(ctx, 1, 3, 11))
            .toEqual([recurringBlockCourseGroup, extraDateCourseGroup])
    })

    it('builds recurrence week selector options from the largest selected interval', () => {
        const methods = (Overview as any).methods
        const ctx = {
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

    it('keeps timetable weekday columns at equal widths', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/overview/Overview.vue',
            'utf8',
        )

        expect(componentSource).toContain('class="timetable-hour-column"')
        expect(componentSource).toContain('class="timetable-day-column"')
        expect(componentSource).toContain('table-layout: fixed;')
        expect(componentSource).toContain('overflow-wrap: anywhere;')
    })
})
