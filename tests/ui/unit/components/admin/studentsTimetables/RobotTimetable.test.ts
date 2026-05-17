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
        expect(componentSource).toContain('robot-generated-cell--occasional')
        expect(componentSource).toContain('generatedSlotDateLabel(slot)')
        expect(componentSource).toContain('timetableWithOccasionalSlots')
        expect(componentSource).toContain('robot-problems')
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
