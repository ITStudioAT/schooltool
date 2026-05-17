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
        expect(componentSource).toContain('Array.from({ length: 8 }')
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
        expect(componentSource).toContain('availableCourseColumns()')
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
        expect(componentSource).toContain('robot-generator__actions')
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
        expect(componentSource).toContain('mergeTimetableOptionsBySlots(options)')
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
        expect(componentSource).toContain('generatedSlotConflictBlocks(slot)')
        expect(componentSource).toContain('robot-generated-cell--occasional')
        expect(componentSource).toContain('robot-generated-cell--affected')
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
        expect(componentSource).toContain('robot-course-columns')
        expect(componentSource).toContain('Keine passenden Kurse gefunden.')
        expect(componentSource).toContain('Kurse')
    })

    it('treats imported TT Grp course slots as alternatives', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'INF', code: 'INF', name: 'Informatik', branch: 'alle', hours: 2 },
            ],
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', course: 'INF', subject: 'INF', weekday: 1, hour: 13 },
                { key: 'inf-fr', class_name: 'INF1-Grp2-KRO', course: 'INF', subject: 'INF', weekday: 5, hour: 12 },
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
        expect(ctx.generatedTimetables).toHaveLength(2)
        expect(ctx.generatedTimetables.map(timetable => Object.keys(timetable.slots).sort())).toEqual([
            ['1-13'],
            ['5-12'],
        ])
        expect(ctx.generatedTimetables.map(timetable => Object.values(timetable.slots)[0].sourceLabel)).toEqual([
            'INF1-Grp1-KRO',
            'INF1-Grp2-KRO',
        ])
        expect(ctx.generatedTimetables.map(timetable => Object.values(timetable.slots)[0].sourceLabel)).not.toContain(
            'INF1-Grp1-KRO + INF1-Grp2-KRO',
        )
        expect(ctx.generatedTimetables[0].slots['4-1']).toBeUndefined()
    })

    it('groups same-slot course alternatives inside one generated timetable cell', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                { key: 'd-gos', class_name: 'D1-1C-GOS', course: 'D1', subject: 'D', weekday: 1, hour: 1 },
                { key: 'd-her', class_name: 'D1-1C-HER', course: 'D1', subject: 'D', weekday: 1, hour: 1 },
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
        expect(ctx.generatedTimetables[0].slots['1-1'].code).toBe('D1')
        expect(ctx.generatedTimetables[0].slots['1-1'].alternativeLabels).toEqual([
            'D1-1C-GOS',
            'D1-1C-HER',
        ])
        expect(methods.generatedSlotDetails.call(ctx, ctx.generatedTimetables[0].slots['1-1'])).toBe(
            'D1-1C-GOS\noder\nD1-1C-HER',
        )
    })

    it('returns more than the first ten generated timetable possibilities', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'D1', code: 'D1', name: 'Deutsch 1', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: Array.from({ length: 11 }, (value, index) => ({
                key: `d-${index + 1}`,
                class_name: `D1-1C-${index + 1}`,
                course: 'D1',
                subject: 'D',
                weekday: 1,
                hour: index + 1,
            })),
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generatedTimetables).toHaveLength(11)
    })

    it('does not combine alternative Grp options to satisfy course hours', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', course: 'INF', subject: 'INF', weekday: 1, hour: 13 },
                { key: 'inf-fr', class_name: 'INF1-Grp2-KRO', course: 'INF', subject: 'INF', weekday: 5, hour: 12 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        const options = methods.timetableOptionsForCourse.call(ctx, {
            key: 'INF',
            code: 'INF',
            name: 'Informatik',
            hours: 2,
        })

        expect(options.map(option => option.label)).toEqual(['INF1-Grp1-KRO', 'INF1-Grp2-KRO'])
    })

    it('does not combine different module course options with the same leading code', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            configuredCourseGroups: [
                { key: 'eth-ck', class_name: 'ETH1-1CK-PLÖ', course: 'ETH', subject: 'ETH', weekday: 2, hour: 11 },
                { key: 'eth-ru', class_name: 'ETH1-1RU-PLÖC', course: 'ETH', subject: 'ETH', weekday: 5, hour: 7 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        const options = methods.timetableOptionsForCourse.call(ctx, {
            key: 'ETH1',
            code: 'ETH1',
            name: 'Ethik',
            hours: 2,
        })

        expect(options.map(option => option.label)).toEqual(['ETH1-1CK-PLÖ', 'ETH1-1RU-PLÖC'])
        expect(options.map(option => option.label)).not.toContain('ETH1-1CK-PLÖ + ETH1-1RU-PLÖC')
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

    it('does not match a different numbered module through a broad TT subject code', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            subjectMappings: [
                { json_subject: 'ÖKO', tt_subject: 'OKON', is_active: true },
            ],
        }
        const course = {
            code: 'ÖKO2 / ÖKO3',
            ttCodes: ['OKON2', 'OKON3'],
        }

        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'OKON2- BIE',
            course: 'OKON',
            subject: 'OKON',
        }, course)).toBe(true)

        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'OKON1- PLA',
            course: 'OKON',
            subject: 'OKON',
        }, course)).toBe(false)

        expect(methods.courseGroupMatchesCourse.call(ctx, {
            class_name: 'ÖKO1- PLA',
            course: 'ÖKO',
            subject: 'ÖKO',
        }, course)).toBe(false)
    })

    it('expands split ÖKO course codes to numbered OKON timetable aliases through mappings', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selection: {
                religion: 'ETH',
                language: 'L',
            },
            subjectMappings: [
                { json_subject: 'ÖKO', tt_subject: 'OKON', is_active: true },
            ],
        }

        expect(methods.selectedCourseTimetableCodes.call(ctx, {
            json_code: 'ÖKO2 / ÖKO3',
            json_subject: 'ÖKO',
        })).toEqual(['OKON2', 'OKON3'])

        expect(methods.courseCodeAliases.call(ctx, {
            code: 'ÖKO2 / ÖKO3',
        })).toEqual(['ÖKO2', 'ÖKO3'])
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

    it('uses exact mapped TT codes when module expansion would miss the imported course', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            subjectMappings: [
                { json_subject: 'LPT', tt_subject: 'LET', is_active: true },
            ],
            configuredCourseGroups: [
                { key: 'lpt', class_name: 'LPT-1CK-DREI', course: 'LET', subject: 'LET', weekday: 4, hour: 10 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }
        const timetableCodes = methods.selectedCourseTimetableCodes.call(ctx, {
            json_code: 'LPT1',
            json_subject: 'LPT',
        })

        expect(timetableCodes).toEqual(['LET1', 'LET'])
        expect(methods.timetableOptionsForCourse.call(ctx, {
            key: 'LPT1',
            code: 'LPT1',
            name: 'LPT',
            hours: 1,
            ttCodes: timetableCodes,
        }).map(option => option.label)).toEqual(['LPT-1CK-DREI'])
    })

    it('marks one-off TT groups orange in the generated timetable', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'INF', code: 'INF', name: 'Informatik', branch: 'alle', hours: 2 },
                { key: 'LPT', code: 'LPT', name: 'LPT', branch: 'alle', hours: 2, ttCodes: ['LET'] },
            ],
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', course: 'INF', subject: 'INF', weekday: 1, hour: 13, dates_count: 18 },
                { key: 'inf-fr', class_name: 'INF1-Grp2-KRO', course: 'INF', subject: 'INF', weekday: 5, hour: 12, dates_count: 18 },
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
        expect(ctx.generatedTimetables).toHaveLength(2)
        expect(Object.keys(ctx.generatedTimetables[0].slots).sort()).toEqual(['1-13', '4-10'])
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
        expect(result.slots['2-14'].conflicts).toMatchObject([
            { label: 'LPT - LPT Di, 17.02.2026 20:25-21:10', sortValue: '2026-02-17-14-LPT - LPT' },
        ])
        expect(result.slots['2-15'].conflicts).toMatchObject([
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

    it('renders conflict cells as both conflicting course blocks', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
        }
        const blocks = methods.generatedSlotConflictBlocks.call(ctx, {
            code: 'INF3',
            name: 'Informatik 3',
            sourceLabel: 'INF3-8AB-MAY',
            courseGroup: { class_name: 'INF3-8AB-MAY', weekday: 5, hour: 13 },
            conflicts: [
                {
                    label: 'ÖKO2 - Ökonomie 19:30-20:15',
                    sortValue: '13-ÖKO2',
                    code: 'ÖKO2',
                    name: 'Ökonomie',
                    sourceLabel: 'ÖKO2- BIE',
                    courseGroup: { class_name: 'ÖKO2- BIE', weekday: 5, hour: 13 },
                },
            ],
        })

        expect(blocks.map(block => block.code)).toEqual(['ÖKO2', 'INF3'])
        expect(blocks.map(block => methods.generatedSlotDetails.call(ctx, block))).toEqual([
            'ÖKO2- BIE',
            'INF3-8AB-MAY',
        ])
    })

    it('does not render red conflicts when same weekday and hour have different dates', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            schoolHours: [
                { hour: 7, from: '14:45:00', until: '15:30:00' },
            ],
        }
        const assignedSlots = {
            '5-7': {
                code: 'ÖKO2',
                name: 'Ökonomie',
                sourceLabel: 'ÖKO2- BIE',
                courseGroup: {
                    class_name: 'ÖKO2- BIE',
                    weekday: 5,
                    hour: 7,
                    dates: ['2026-02-20'],
                },
            },
        }
        const candidateSet = {
            course: { code: 'INF3', name: 'Informatik 3' },
            options: [
                {
                    label: 'INF3-8AB-MAY',
                    courseGroups: [
                        {
                            class_name: 'INF3-8AB-MAY',
                            weekday: 5,
                            hour: 7,
                            dates: ['2026-02-27'],
                        },
                    ],
                },
            ],
        }

        const result = methods.assignedSlotsWithCourseConflicts.call(
            ctx,
            candidateSet,
            assignedSlots,
            new Set(['5-7']),
        )

        expect(result['5-7'].conflicts).toBeUndefined()
        expect(methods.candidateSetConflictLabels.call(ctx, candidateSet, assignedSlots, new Set(['5-7']))).toEqual([])
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

    it('splits available courses into two display columns', () => {
        const computed = (RobotTimetable as any).computed
        const ctx = {
            availableCourses: [
                { key: 'D1', code: 'D1' },
                { key: 'E1', code: 'E1' },
                { key: 'INF1', code: 'INF1' },
                { key: 'M1', code: 'M1' },
                { key: 'ME1', code: 'ME1' },
            ],
        }

        expect(computed.availableCourseColumns.call(ctx).map(courseColumn =>
            courseColumn.map(course => course.code),
        )).toEqual([
            ['D1', 'E1', 'INF1'],
            ['M1', 'ME1'],
        ])
    })

    it('updates available courses when the robot selection changes', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            subjectMappings: [],
            religionOptions: computed.religionOptions.call({}),
            branchOptions: computed.branchOptions.call({}),
            artsSubjectOptions: computed.artsSubjectOptions.call({}),
            languageOptions: computed.languageOptions.call({}),
            subjectRows: [
                {
                    id: 1,
                    semester: 1,
                    branch: 'common',
                    json_code: 'D1',
                    json_subject: 'D',
                    name: 'Deutsch 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 2,
                    branch: 'common',
                    json_code: 'D2',
                    json_subject: 'D',
                    name: 'Deutsch 2',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 1,
                    branch: 'wirtschaftskundlich',
                    json_code: 'WIK1',
                    json_subject: 'WIK',
                    name: 'Wiku 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 4,
                    semester: 1,
                    branch: 'gymnasial',
                    json_code: 'GYM1',
                    json_subject: 'GYM',
                    name: 'Gym 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 5,
                    semester: 1,
                    branch: 'common',
                    json_code: 'ME1',
                    json_subject: 'ME',
                    name: 'Musikerziehung 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 6,
                    semester: 1,
                    branch: 'common',
                    json_code: 'BE1',
                    json_subject: 'BE',
                    name: 'Bildnerische Erziehung 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 7,
                    semester: 1,
                    branch: 'common',
                    json_code: 'R/ET1',
                    json_subject: 'R/ET',
                    name: 'Religion / Ethik 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 8,
                    semester: 1,
                    branch: 'common',
                    json_code: 'L/F/S1',
                    json_subject: 'L/F/S',
                    name: 'Sprache 1',
                    hours_per_week: 2,
                    is_active: true,
                },
            ],
        }
        const courses = () => computed.availableCourses.call(ctx)
        const courseCodes = () => courses().map(course => course.code)

        expect(courseCodes()).toEqual(['D1', 'ETH1', 'L1', 'ME1', 'WIK1'])

        ctx.selection.semester = 2
        expect(courseCodes()).toEqual(['D2'])

        ctx.selection.semester = 1
        ctx.selection.branch = 'gymnasial'
        expect(courseCodes()).toEqual(['D1', 'ETH1', 'GYM1', 'L1', 'ME1'])

        ctx.selection.artsSubject = 'BE'
        expect(courseCodes()).toEqual(['BE1', 'D1', 'ETH1', 'GYM1', 'L1'])

        ctx.selection.language = 'F'
        expect(courseCodes()).toEqual(['BE1', 'D1', 'ETH1', 'F1', 'GYM1'])

        const lateinCourseKey = courses().find(course => course.code === 'F1')?.key
        expect(lateinCourseKey).toContain('F1')

        ctx.selection.religion = 'Rk'
        expect(courseCodes()).toEqual(['BE1', 'D1', 'F1', 'GYM1', 'Rk1'])
        expect(courses().find(course => course.code === 'Rk1')?.key).toContain('Rk1')
    })

    it('splits ordinary slash-separated subject codes into separate robot courses', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            selection: {
                semester: 8,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            subjectMappings: [
                { json_subject: 'ÖKO', tt_subject: 'OKON', is_active: true },
            ],
            languageOptions: [
                { title: 'L - Latein', value: 'L' },
            ],
            subjectRows: [
                {
                    id: 1,
                    semester: 8,
                    branch: 'common',
                    json_code: 'ÖKO2 / ÖKO3',
                    json_subject: 'ÖKO',
                    name: 'Ökologie',
                    hours_per_week: 4,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 8,
                    branch: 'common',
                    json_code: 'L/F/S2',
                    json_subject: 'L/F/S',
                    name: 'Sprache',
                    hours_per_week: 2,
                    is_active: true,
                },
            ],
        }

        const courses = computed.availableCourses.call(ctx)

        expect(courses.map(course => course.code)).toEqual(['L2', 'ÖKO2', 'ÖKO3'])
        expect(courses.find(course => course.code === 'ÖKO2').hours).toBe(2)
        expect(courses.find(course => course.code === 'ÖKO3').hours).toBe(2)
        expect(courses.find(course => course.code === 'ÖKO2').ttCodes).toEqual(['OKON2'])
        expect(courses.find(course => course.code === 'ÖKO3').ttCodes).toEqual(['OKON3'])
    })

    it('generates ÖKO2 and ÖKO3 after splitting shared subject hours', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'ÖKO2', code: 'ÖKO2', name: 'Ökonomie', branch: 'Wiku', hours: 2, ttCodes: ['OKON2'] },
                { key: 'ÖKO3', code: 'ÖKO3', name: 'Ökonomie', branch: 'Wiku', hours: 2, ttCodes: ['OKON3'] },
            ],
            configuredCourseGroups: [
                { key: 'oko2-7', class_name: 'ÖKO2- BIE', course: 'OKON', subject: 'OKON', weekday: 5, hour: 7 },
                { key: 'oko2-13', class_name: 'ÖKO2- BIE', course: 'OKON', subject: 'OKON', weekday: 5, hour: 13 },
                { key: 'oko3-8', class_name: 'ÖKO3- BIE', course: 'OKON', subject: 'OKON', weekday: 5, hour: 8 },
                { key: 'oko3-9', class_name: 'ÖKO3- BIE', course: 'OKON', subject: 'OKON', weekday: 5, hour: 9 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: Array.from({ length: 15 }, (value, index) => index + 1),
            },
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationProblems).toEqual([])
        expect(ctx.generatedTimetables).toHaveLength(1)
        expect(Object.keys(ctx.generatedTimetables[0].slots).sort()).toEqual(['5-13', '5-7', '5-8', '5-9'])
    })

    it('shows Monday to Friday and only shows Saturday when selected', () => {
        const computed = (RobotTimetable as any).computed
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
                { title: 'Mittwoch', shortTitle: 'Mi', value: 3 },
                { title: 'Donnerstag', shortTitle: 'Do', value: 4 },
                { title: 'Freitag', shortTitle: 'Fr', value: 5 },
                { title: 'Samstag', shortTitle: 'Sa', value: 6 },
            ],
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5],
            },
        }

        expect(computed.generatedWeekdays.call(ctx).map(weekday => weekday.shortTitle)).toEqual([
            'Mo',
            'Di',
            'Mi',
            'Do',
            'Fr',
        ])

        ctx.constraints.availableWeekdays.push(6)

        expect(computed.generatedWeekdays.call(ctx).map(weekday => weekday.shortTitle)).toEqual([
            'Mo',
            'Di',
            'Mi',
            'Do',
            'Fr',
            'Sa',
        ])
    })

    it('creates best possible timetables and reports problems when courses overlap', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'INF1', code: 'INF1', name: 'Informatik 1', branch: 'alle', hours: 2 },
                { key: 'GW1', code: 'GW1', ttCode: 'GWB1', name: 'Geografie 1', branch: 'alle', hours: 2 },
            ],
            configuredCourseGroups: [
                { key: 'inf-mo', class_name: 'INF1-Grp1-KRO', weekday: 1, hour: 1 },
                { key: 'inf-tu', class_name: 'INF1-Grp1-KRO', weekday: 1, hour: 3 },
                { key: 'gw-mo', class_name: 'GWB1-1C-HÖF', course: 'GWB', subject: 'GWB', weekday: 1, hour: 1 },
                { key: 'gw-tu', class_name: 'GWB1-1C-HÖF', course: 'GWB', subject: 'GWB', weekday: 1, hour: 2 },
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
                { title: '2. Stunde', shortTitle: '2. Stunde', value: 2 },
                { title: '3. Stunde', shortTitle: '3. Stunde', value: 3 },
            ],
            schoolHours: [
                { hour: 1, from: '18:45:00', until: '19:30:00' },
                { hour: 2, from: '19:30:00', until: '20:15:00' },
                { hour: 3, from: '20:25:00', until: '21:10:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables.length).toBeGreaterThan(0)
        expect(Object.keys(ctx.generatedTimetables[0].slots)).toHaveLength(3)
        expect(ctx.generatedTimetables[0].problems.length).toBeGreaterThan(0)
        expect(ctx.generationProblems.length).toBeGreaterThan(0)
        expect(methods.generatedSlotConflicts.call(ctx, ctx.generatedTimetables[0].slots['1-1'])).toEqual([
            'GW1 - Geografie 1 18:45-19:30',
        ])
        expect(ctx.generatedTimetables[0].slots['1-2'].code).toBe('GW1')
        expect(ctx.generatedTimetables[0].slots['1-2'].isConflictPreview).toBe(true)
        expect(methods.generatedSlotConflicts.call(ctx, ctx.generatedTimetables[0].slots['1-2'])).toEqual([])
    })

    it('does not show timetable variants where a course is softly skipped', () => {
        const methods = (RobotTimetable as any).methods
        const ctx = {
            ...methods,
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            selectedCourses: [
                { key: 'D7', code: 'D7', name: 'Deutsch 7', branch: 'alle', hours: 1 },
                { key: 'M7', code: 'M7', name: 'Mathematik 7', branch: 'alle', hours: 1 },
                { key: 'E7', code: 'E7', name: 'Englisch 7', branch: 'alle', hours: 1 },
            ],
            configuredCourseGroups: [
                { key: 'd7', class_name: 'D7-7C-DREI', course: 'D7', subject: 'D', weekday: 1, hour: 1 },
                { key: 'm7', class_name: 'M7-7C-MAL', course: 'M7', subject: 'M', weekday: 1, hour: 2 },
                { key: 'e7', class_name: 'E7-7C-RAI', course: 'E7', subject: 'E', weekday: 1, hour: 2 },
            ],
            constraints: {
                availableWeekdays: [1],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2],
            },
            weekdayOptions: [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
            ],
            timeOptions: [
                { title: '1. Stunde', shortTitle: '1. Stunde', value: 1 },
                { title: '2. Stunde', shortTitle: '2. Stunde', value: 2 },
            ],
            schoolHours: [
                { hour: 1, from: '17:50:00', until: '18:35:00' },
                { hour: 2, from: '18:45:00', until: '19:30:00' },
            ],
        }

        methods.generateTimetables.call(ctx)

        expect(ctx.generationError).toBe('')
        expect(ctx.generatedTimetables.length).toBeGreaterThan(0)
        expect(ctx.generatedTimetables.flatMap(timetable => timetable.problems).some(problem =>
            problem.includes('in dieser Variante nicht eingeplant'),
        )).toBe(false)
        expect(ctx.generationProblems.some(problem => problem.includes('in dieser Variante nicht eingeplant'))).toBe(false)
        expect(ctx.generatedTimetables.some(timetable =>
            Object.values(timetable.slots).some(slot => methods.generatedSlotConflicts.call(ctx, slot).length),
        )).toBe(true)
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
