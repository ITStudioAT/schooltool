import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import RobotTimetable from '@/pages/admin/studentsTimetables/robot/RobotTimetable.vue'

describe('Students timetable robot page', () => {
    it('provides selectable robot timetable criteria', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/robot/RobotTimetable.vue',
            'utf8',
        )

        expect(componentSource).toContain('Roboter Stundenplan')
        expect(componentSource).toContain('semesterOptions()')
        expect(componentSource).toContain('Array.from({ length: 9 }')
        expect(componentSource).toContain('label="Semester"')
        expect(componentSource).toContain('label="Ethik / Religion"')
        expect(componentSource).toContain('label="Zweig"')
        expect(componentSource).toContain('label="ME / BE"')
        expect(componentSource).toContain('label="Sprache"')
        expect(componentSource).toContain('Tage, an denen ich kann')
        expect(componentSource).toContain('Diese Stunden verwenden')
        expect(componentSource).toContain('Einzelne Zeiten sperren')
        expect(componentSource).toContain("ETH - Ethik")
        expect(componentSource).toContain("Rev - Religion evangelisch")
        expect(componentSource).toContain("Ris - Religion Islam")
        expect(componentSource).toContain("Rk - Religion katholisch")
        expect(componentSource).toContain("Ror - Religion orthodox")
        expect(componentSource).toContain("Wirtschaftskundlicher Zweig")
        expect(componentSource).toContain("Gymnasialer Zweig")
        expect(componentSource).toContain("ME - Musikerziehung")
        expect(componentSource).toContain("BE - Bildnerische Erziehung")
        expect(componentSource).toContain("L - Latein")
        expect(componentSource).toContain("F - Französisch")
        expect(componentSource).toContain("S - Spanisch")
        expect(componentSource).toContain('weekdayOptions()')
        expect(componentSource).toContain('timeOptions()')
        expect(componentSource).toContain('weekdayTimeOptions()')
        expect(componentSource).toContain('selectedConstraintSummary()')
        expect(componentSource).toContain('availableWeekdays: [1, 2, 3, 4, 5, 6]')
        expect(componentSource).toContain('unavailableWeekdays()')
        expect(componentSource).toContain('selectedOptionTitles(options, values)')
        expect(componentSource).toContain('constraintSelected(key, value)')
        expect(componentSource).toContain('toggleConstraint(key, value)')
        expect(componentSource).toContain('weekdayTimeValue(weekday, time)')
        expect(componentSource).toContain('weekdayTimeAvailable(weekday, time)')
        expect(componentSource).toContain('toggleWeekdayTime(weekday, time)')
        expect(componentSource).toContain("weekdayTimeAvailable(weekday.value, time.value) ? 'success' : 'error'")
        expect(componentSource).toContain('robot-chip-row')
        expect(componentSource).toContain('robot-time-matrix')
        expect(componentSource).toContain('@click="toggleWeekdayTime')
        expect(componentSource).toContain('Montag')
        expect(componentSource).toContain('Samstag')
        expect(componentSource).toContain('1}. Stunde')
        expect(componentSource).toContain('Zeitvorgaben')
        expect(componentSource).toContain('Nicht möglich')
        expect(componentSource).toContain('Keine Zeiten')
        expect(componentSource).toContain('availableTimes: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]')
        expect(componentSource).toContain('unavailableTimes()')
        expect(componentSource).toContain('Nicht verwendete Stunden')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/school-hours')")
        expect(componentSource).toContain('defaultTimeOptions()')
        expect(componentSource).toContain('timeOptionTitle(schoolHour)')
        expect(componentSource).toContain('timeOptionShortTitle(schoolHour)')
        expect(componentSource).toContain('timeRangeLabel(schoolHour)')
        expect(componentSource).toContain('formatTimeValue(value)')
        expect(componentSource).toContain('syncAvailableTimes()')
        expect(componentSource).toContain('selectedSummary()')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/subjects-overview-settings')")
        expect(componentSource).toContain('subjectMappings')
        expect(componentSource).toContain('deselectedCourseKeys')
        expect(componentSource).toContain('availableCourses()')
        expect(componentSource).toContain('selectedCourses()')
        expect(componentSource).toContain('selectedCoursesHours()')
        expect(componentSource).toContain('courseSelected(course)')
        expect(componentSource).toContain('setCourseSelected(course, selected)')
        expect(componentSource).toContain('selectAllCourses()')
        expect(componentSource).toContain('deselectAllCourses()')
        expect(componentSource).toContain('Alle auswählen')
        expect(componentSource).toContain('Alle abwählen')
        expect(componentSource).toContain('@update:model-value="setCourseSelected(course, $event)"')
        expect(componentSource).toContain('Stundenpläne erstellen')
        expect(componentSource).toContain('generateTimetables()')
        expect(componentSource).toContain('generatedTimetables')
        expect(componentSource).toContain('generationProblems')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/course-groups')")
        expect(componentSource).toContain('configuredCourseGroups()')
        expect(componentSource).toContain('timetableCandidateSets(courses)')
        expect(componentSource).toContain('timetableOptionsForCourse(course, courseGroups = null, completeOptions = true)')
        expect(componentSource).toContain('courseGroupMatchesCourse(courseGroup, course)')
        expect(componentSource).toContain('isOccasionalCourseGroup(courseGroup)')
        expect(componentSource).toContain('defaultTimetableCodeAlias(value)')
        expect(componentSource).toContain('buildTimetableCombinations(')
        expect(componentSource).toContain('keine passenden TT-Stunden gefunden')
        expect(componentSource).toContain('generatedWeekdays()')
        expect(componentSource).toContain('generatedTimes()')
        expect(componentSource).toContain('clearGeneratedTimetables()')
        expect(componentSource).toContain('Stundenplan {{ timetable.number }}')
        expect(componentSource).toContain('robot-generated-grid')
        expect(componentSource).toContain('robot-generated-cell__details')
        expect(componentSource).toContain('generatedSlotDetails(slot)')
        expect(componentSource).toContain('robot-generated-cell--conflict')
        expect(componentSource).toContain('robot-generated-cell__conflicts')
        expect(componentSource).toContain('generatedSlotConflicts(slot)')
        expect(componentSource).toContain('robot-generated-cell--occasional')
        expect(componentSource).toContain('generatedSlotDateLabel(slot)')
        expect(componentSource).toContain('timetableWithOccasionalSlots')
        expect(componentSource).toContain('robot-problems')
        expect(componentSource).toContain('courseGroupDateTimeLabel(courseGroup)')
        expect(componentSource).toContain('courseGroupDateTimeLabels(courseGroup)')
        expect(componentSource).toContain('courseGroupDateTimeEntries(courseGroup)')
        expect(componentSource).toContain('formatDateWithWeekdayLabel(value)')
        expect(componentSource).toContain('weekdayLabelForDate(value)')
        expect(componentSource).toContain('trackOccasionalCourseConflict(conflicts, course, courseGroup, existingSlot)')
        expect(componentSource).toContain('problemSummary(problem)')
        expect(componentSource).toContain('problemDetails(problem)')
        expect(componentSource).toContain('robot-problems__details')
        expect(componentSource).toContain('candidateSetConflictProblemMessage')
        expect(componentSource).toContain('subjectMatchesSelectedBranch(subject)')
        expect(componentSource).toContain('subjectMatchesSelectedChoices(subject)')
        expect(componentSource).toContain('selectedCourseFromSubject(subject)')
        expect(componentSource).toContain('selectedCourseCode(subject)')
        expect(componentSource).toContain('selectedCourseName(subject)')
        expect(componentSource).toContain('isReligionSubject(subject)')
        expect(componentSource).toContain('isLanguageSubject(subject)')
        expect(componentSource).toContain('isArtsSubject(subject)')
        expect(componentSource).toContain('robot-course-table')
        expect(componentSource).toContain('Keine passenden Kurse gefunden.')
        expect(componentSource).toContain('Kurse')
    })

    it('generates only from imported TT course slots', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'INF1', code: 'INF1', name: 'Informatik 1', branch: 'alle', hours: 2 },
            ],
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', weekday: 1, hour: 13 },
                { key: 'inf-fr', class_name: 'INF1-Grp2-KRO', weekday: 5, hour: 12 },
                { key: 'inf2-wrong', class_name: 'INF2-7C-STRA', weekday: 4, hour: 1 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables).toHaveLength(1)
        expect(Object.keys(ctx.generatedTimetables[0].slots).sort()).toEqual(['1-13', '5-12'])
        expect(ctx.generatedTimetables[0].slots['4-1']).toBeUndefined()
    })

    it('matches subject overview codes against imported TT aliases', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }

        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'GWB1-1C-HÖF',
            course: 'GWB',
            subject: 'GWB',
        }, {
            code: 'GW1',
            ttCode: 'GWB1',
        })).toBe(true)

        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'LPT-1CK-DREI',
            course: 'LET',
            subject: 'LET',
        }, {
            code: 'LPT',
            ttCode: 'LET',
        })).toBe(true)
    })

    it('uses the configured Fach-Zuordnung for TT matching', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            subjectMappings: [
                { json_subject: 'GW', tt_subject: 'GWB', is_active: true },
                { json_subject: 'LPT', tt_subject: 'LET', is_active: true },
                { json_subject: 'ME', tt_subject: 'MU', is_active: false },
            ],
        }

        expect(methods.selectedCourseTimetableCodes.call(ctx, {
            json_code: 'GW1',
            json_subject: 'GW',
        })).toEqual(['GWB1'])

        expect(methods.selectedCourseTimetableCodes.call(ctx, {
            json_code: 'LPT',
            json_subject: 'LPT',
        })).toEqual(['LET'])
    })

    it('marks one-off TT groups orange in the generated timetable', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'INF1', code: 'INF1', name: 'Informatik 1', branch: 'alle', hours: 2 },
                { key: 'LPT', code: 'LPT', name: 'LPT', branch: 'alle', hours: 2, ttCodes: ['LET'] },
            ],
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', weekday: 1, hour: 13, dates_count: 18 },
                { key: 'inf-fr', class_name: 'INF1-Grp2-KRO', weekday: 5, hour: 12, dates_count: 18 },
                {
                    key: 'lpt-once',
                    class_name: 'LPT-1CK-DREI',
                    course: 'LET',
                    subject: 'LET',
                    weekday: 4,
                    hour: 10,
                    dates_count: 1,
                    dates: ['2026-09-30'],
                },
            ],
            schoolHours: [
                { hour: 10, from: '17:05:00', until: '17:50:00' },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables).toHaveLength(1)
        expect(Object.keys(ctx.generatedTimetables[0].slots).sort()).toEqual(['1-13', '4-10', '5-12'])
        expect(ctx.generatedTimetables[0].slots['4-10'].isOccasional).toBe(true)
        expect(methods.generatedSlotDateLabel.call(ctx, ctx.generatedTimetables[0].slots['4-10'])).toBe('30.09.2026')
        expect(ctx.generatedTimetables[0].problems[0]).toContain('orange im Stundenplan markiert')
        expect(ctx.generatedTimetables[0].problems[0]).toContain('Mi, 30.09.2026 17:05-17:50')
    })

    it('keeps one-off overlap problems grouped while showing time ranges', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            schoolHours: [
                { hour: 14, from: '20:25:00', until: '21:10:00' },
                { hour: 15, from: '21:10:00', until: '21:55:00' },
            ],
        }

        const result = methods.timetableWithOccasionalSlots.call(ctx, {
            slots: {
                '2-14': { code: 'D1', name: 'Deutsch 1' },
                '2-15': { code: 'D1', name: 'Deutsch 1' },
            },
            problems: [],
        }, [
            {
                course: { code: 'LPT', name: 'LPT' },
                occasionalOptions: [
                    {
                        courseGroups: [
                            { weekday: 2, hour: 14, dates: ['2026-02-17'] },
                            { weekday: 2, hour: 15, dates: ['2026-02-17'] },
                        ],
                    },
                ],
            },
        ])

        expect(result.problems).toEqual([
            'LPT - LPT: Einzeltermin Di, 17.02.2026 20:25-21:10 <-> D1 - Deutsch 1 Di, 17.02.2026 20:25-21:10, Di, 17.02.2026 21:10-21:55 <-> D1 - Deutsch 1 Di, 17.02.2026 21:10-21:55 überschneidet sich mit D1 - Deutsch 1.',
        ])
        expect(result.slots['2-14'].conflicts).toEqual([
            { label: 'LPT - LPT Di, 17.02.2026 20:25-21:10', sortValue: '2026-02-17-14-LPT - LPT' },
        ])
        expect(result.slots['2-15'].conflicts).toEqual([
            { label: 'LPT - LPT Di, 17.02.2026 21:10-21:55', sortValue: '2026-02-17-15-LPT - LPT' },
        ])
        expect(methods.generatedSlotConflicts.call(ctx, result.slots['2-14'])).toEqual([
            'LPT - LPT Di, 17.02.2026 20:25-21:10',
        ])
    })

    it('sorts generated slot conflicts by date', () => {
        const methods = (RobotTimetable as any).methods

        expect(methods.generatedSlotConflicts.call({}, {
            conflicts: [
                { label: 'GW1 - Geografie 1 Fr, 13.03.2026 14:45-15:30', sortValue: '2026-03-13-7-GW1' },
                { label: 'LPT - LPT Fr, 20.02.2026 14:45-15:30', sortValue: '2026-02-20-7-LPT' },
            ],
        })).toEqual([
            'LPT - LPT Fr, 20.02.2026 14:45-15:30',
            'GW1 - Geografie 1 Fr, 13.03.2026 14:45-15:30',
        ])
    })

    it('formats one-off appointments as indented problem details', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }

        const onlyOccasionalProblem = 'LPT - LPT: nur Einzeltermine (Di, 17.02.2026 20:25-21:10, Mi, 18.02.2026 17:05-17:50), orange im Stundenplan markiert.'
        const conflictProblem = 'LPT - LPT: Einzeltermin Di, 17.02.2026 20:25-21:10 <-> D1 - Deutsch 1 Di, 17.02.2026 20:25-21:10, Di, 17.02.2026 21:10-21:55 <-> D1 - Deutsch 1 Di, 17.02.2026 21:10-21:55 überschneidet sich mit D1 - Deutsch 1.'

        expect(methods.problemSummary.call(ctx, onlyOccasionalProblem)).toBe(
            'LPT - LPT: nur Einzeltermine, orange im Stundenplan markiert.',
        )
        expect(methods.problemDetails.call(ctx, onlyOccasionalProblem)).toEqual([
            'Di, 17.02.2026 20:25-21:10',
            'Mi, 18.02.2026 17:05-17:50',
        ])
        expect(methods.problemSummary.call(ctx, conflictProblem)).toBe(
            'LPT - LPT: Einzeltermin überschneidet sich mit D1 - Deutsch 1.',
        )
        expect(methods.problemDetails.call(ctx, conflictProblem)).toEqual([
            'Di, 17.02.2026 20:25-21:10 <-> D1 - Deutsch 1 Di, 17.02.2026 20:25-21:10',
            'Di, 17.02.2026 21:10-21:55 <-> D1 - Deutsch 1 Di, 17.02.2026 21:10-21:55',
        ])
    })

    it('can select and deselect courses for timetable generation', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            deselectedCourseKeys: ['GW1'],
            availableCourses: [
                { key: 'INF1', code: 'INF1', name: 'Informatik 1', hours: 2 },
                { key: 'GW1', code: 'GW1', name: 'Geografie 1', hours: 4 },
            ],
            clearGeneratedTimetables() {},
        }

        expect(computed.selectedCourses.call(ctx).map(course => course.code)).toEqual(['INF1'])

        methods.setCourseSelected.call(ctx, ctx.availableCourses[1], true)
        expect(ctx.deselectedCourseKeys).toEqual([])
        expect(computed.selectedCourses.call(ctx).map(course => course.code)).toEqual(['INF1', 'GW1'])

        methods.deselectAllCourses.call(ctx)
        expect(computed.selectedCourses.call(ctx)).toEqual([])
    })

    it('creates best possible timetables and reports problems when courses overlap', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'INF1', code: 'INF1', name: 'Informatik 1', branch: 'alle', hours: 1 },
                { key: 'GW1', code: 'GW1', ttCode: 'GWB1', name: 'Geografie 1', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', weekday: 1, hour: 1 },
                { key: 'gw-mo', class_name: 'GWB1-1C-HÖF', course: 'GWB', subject: 'GWB', weekday: 1, hour: 1 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
            ],
            timeOptions: [
                { title: '1. Stunde', shortTitle: '1. Stunde', value: 1 },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables.length).toBeGreaterThan(0)
        expect(Object.keys(ctx.generatedTimetables[0].slots)).toHaveLength(1)
        expect(ctx.generatedTimetables[0].problems.length).toBeGreaterThan(0)
        expect(ctx.generationProblems.length).toBeGreaterThan(0)
    })

    it('shows imported TT details as a normal second line for generated slots', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }

        expect(methods.generatedSlotDetails.call(ctx, {
            sourceLabel: 'INF1-Grp1-KRO',
            courseGroup: {
                teacher: 'KRO',
                rooms: ['EDV1', 'EDV2'],
            },
        })).toBe('INF1-Grp1-KRO · KRO · EDV1, EDV2')
    })
})
