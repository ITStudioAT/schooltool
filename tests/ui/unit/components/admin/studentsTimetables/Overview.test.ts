import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import Overview from '@/pages/admin/studentsTimetables/overview/Overview.vue'

describe('Students timetable overview', () => {
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
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
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
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
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
            activeCourseGroupFilterKeys: ['math', 'bio'],
            configuredCourseGroups: [mathCourseGroup, bioCourseGroup],
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
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
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
            courseMenuEntriesOverlap: methods.courseMenuEntriesOverlap,
            courseGroupsOverlap: methods.courseGroupsOverlap,
        }

        expect(methods.selectedCourseFilterChips.call(ctx, 1).map((filterChip: Record<string, boolean>) => filterChip.hasOverlap))
            .toEqual([true, true])
        expect(methods.courseGroupHasOverlap.call(ctx, mathCourseGroup)).toBe(true)
        expect(methods.courseGroupHasOverlap.call(ctx, bioCourseGroup)).toBe(true)
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
            courseMenuEntryScheduleLabel: methods.courseMenuEntryScheduleLabel,
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
