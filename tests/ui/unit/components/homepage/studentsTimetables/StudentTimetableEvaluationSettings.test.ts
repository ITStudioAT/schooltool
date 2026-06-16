import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import StudentTimetableEvaluationSettings from '@/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue'
import Overview from '@/pages/homepage/studentsTimetables/overview/Overview.vue'

describe('Student timetable evaluation settings', () => {
    it('receives proposed courses from the student overview', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain(':proposed-courses="automaticTimetableSelectableCourses"')
        expect(source).toContain(':initial-step="automaticTimetableStep"')
        expect(source).toContain(':initial-selected-course-keys="automaticTimetableCourseKeys"')
        expect(source).toContain(':initial-selected-quality-criterion-keys="automaticTimetableQualityCriterionKeys"')
        expect(source).toContain(':default-quality-criterion-selection="!automaticTimetableQualityCriteriaSelectionExplicit"')
        expect(source).toContain(':course-sections="automaticTimetableAllCourseSections"')
        expect(source).toContain('automaticTimetableAllCourseSections()')
        expect(source).toContain("String(section?.key || '') === 'additional'")
        expect(source).toContain('this.overview?.additional_courses')
        expect(source).toContain("title: 'Zusätzliche Kurse'")
        expect(source).toContain('@course-selection-change="setAutomaticTimetableCourseKeys"')
        expect(source).toContain('@courses-selected="finishAutomaticTimetable"')
        expect(source).toContain('@quality-criteria-selection-change="setAutomaticTimetableQualityCriterionKeys"')
        expect(source).toContain('@step-change="setAutomaticTimetableStep"')
        expect(source).toContain('automatic_timetable')
        expect(source).toContain('automatic_timetable_courses')
        expect(source).toContain('automatic_timetable_criteria')
        expect(source).toContain("const noAutomaticTimetableQualityCriteriaValue = '__none'")
        expect(source).toContain("this.setAutomaticTimetableStep('criteria')")
        expect(source).toContain("'criteria', 'courses', 'result'")
    })

    it('opens a manual timetable from the student overview', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain('@click="openPublishedTimetable"')
        expect(source).toContain('@click="openPersonalTimetable"')
        expect(source).toContain('@click="adoptPublishedTimetable"')
        expect(source).toContain('@click="openPersonalTimetableDeleteDialog"')
        expect(source).toContain('@click="deletePersonalTimetable"')
        expect(source).toContain('persistent max-width="460"')
        expect(source).toContain('Mein Stundenplan löschen?')
        expect(source).toContain('Dieser Stundenplan wird nur für dich und dieses Schuljahr gelöscht.')
        expect(source).toContain('this.studentTimetablesStore.deletePersonalTimetable()')
        expect(source).toContain('personalTimetableEmptyVisible')
        expect(source).toContain('Mein Stundenplan ist noch leer.')
        expect(source).toContain('personalTimetableViewChangeVisible')
        expect(source).toContain('Der Stundenplan wurde übernommen. Du siehst jetzt "Mein Stundenplan".')
        expect(source).toContain('closable')
        expect(source).toContain('close-label="Meldung schließen"')
        expect(source).toContain('@click:close="dismissPersonalTimetableViewChangeMessage"')
        expect(source).toContain('Als mein Stundenplan übernehmen')
        expect(source).toContain('class="student-course-choice-panel"')
        expect(source).toContain('<span>Kursauswahl</span>')
        expect(source).toContain('@click="openStudentCoursePickerDialog"')
        expect(source).toContain('v-model="studentCoursePickerDialogOpen" persistent max-width="760"')
        expect(source).toContain('Kurs wählen')
        expect(source).toContain('studentCoursePickerTabs')
        expect(source).toContain('Fehlende Kurse')
        expect(source).toContain('Vorgesehene Kurse')
        expect(source).toContain('Zusätzliche Kurse')
        expect(source).toContain('Offene Kurse')
        expect(source).toContain('selectedStudentCoursePickerEntryOptions')
        expect(source).toContain('@click="toggleStudentCoursePickerEntry(entryOption)"')
        expect(source).toContain('{{ savedTimetableCourseChips.length }} ausgewählt')
        expect(source).toContain('@click="deselectAllSavedTimetableCourseChips"')
        expect(source).toContain('@click:close="deselectSavedTimetableCourseChip(courseChip)"')
        expect(source).toContain('savedTimetableCourseChips()')
        expect(source).toContain('studentCoursePickerCourseMenus()')
        expect(source).toContain('publishedTimetableCellRawCourses(cell)')
        expect(source).toContain('Löschen')
        expect(source).toContain('Mein Stundenplan')
        expect(source).toContain('Von der Schule gespeicherter Stundenplan')
        expect(source).toContain('hasPublishedTimetable')
        expect(source).toContain('hasPersonalTimetable')
        expect(source).toContain('personal_timetable')
        expect(source).toContain('published_timetable')
        expect(source).toContain('publishedTimetableVisible')
        expect(source).toContain("manual_timetable = 'personal'")
        expect(source).toContain('class="student-published-timetable__grid"')
        expect(source).toContain('class="timetable-date-overview"')
        expect(source).toContain('@media (max-width: 380px)')
        expect(source).toContain('box-shadow: none;')
        expect(source).toContain('color: #f8fafc;')
        expect(source).toContain('grid-template-columns: 30px repeat(var(--published-timetable-weekday-count, 6), minmax(0, 1fr));')
        expect(source).toContain('showManualTimetable')
        expect(source).toContain('manual_timetable')
        expect(source).toContain('manualTimetableSelection()')
        expect(source).toContain('manualSelectedCourseGroups()')
        expect(source).toContain('manualGroupsForCell(weekday.value, hour.value)')
        expect(source).toContain('<div class="student-manual-timetable__corner">Std.</div>')
        expect(source).toContain('.student-manual-timetable__cell--conflict {\n    background: #fef2f2;')
        expect(source).toContain('<v-checkbox-btn')
        expect(source).toContain("return 'Stundenplan'")
        expect(source).toContain('Keine passenden Kurstermine gefunden.')
        expect(source).toContain('showManualTimetable && courseSections.length')
        expect(source).toContain('class="manual-overview-course-card"')
        expect(source).toContain('v-model="expandedManualOverviewCoursePanels"')
        expect(source).toContain('expandedManualOverviewCoursePanels: []')
        expect(source).toContain('<h3>Kurse</h3>')
        expect(source).toContain('courseSectionTotalCount')
    })

    it('opens a published timetable with the saved course group selection', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const savedCourseGroup = {
            key: 'saved-group',
            course: 'D1',
            weekday: 1,
            hour: 2,
        }
        const otherCourseGroup = {
            key: 'other-group',
            course: 'D1',
            weekday: 2,
            hour: 3,
        }
        const course = {
            key: 'course-1',
            code: 'D1',
            course_groups: [savedCourseGroup, otherCourseGroup],
        }
        const pushedRoutes: unknown[] = []
        const ctx: any = {
            ...methods,
            showManualTimetable: false,
            showEvaluationSettings: true,
            manualTimetableMode: 'manual',
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            overview: {
                published_timetable: {
                    id: 7,
                    active_course_group_keys: ['saved-group'],
                    timetable: {
                        student: '5C · ZADRA Isabella · Semester 5',
                        schoolyear: 'Schuljahr 2025/26',
                        generated_at: '16.06.26, 21:02',
                        weekdays: [
                            { label: 'Mo' },
                            { label: 'Di' },
                        ],
                        semesters: [
                            {
                                label: 'Semester',
                                date_range: '16.02.2026 - 10.07.2026',
                                weeks: [
                                    {
                                        hours: [
                                            {
                                                hour: 10,
                                                from: '17:05',
                                                until: '17:50',
                                                cells: [
                                                    {
                                                        status: 'conflict',
                                                        courses: [
                                                            {
                                                                label: 'D5 - 3R - SHAM',
                                                                details: 'FU · 2-wöchig',
                                                                dates: [
                                                                    '2026-02-24',
                                                                    '2026-03-10',
                                                                    '2026-06-30',
                                                                ],
                                                            },
                                                            {
                                                                label: 'E5 - 3R - HÖF',
                                                                details: '2-wöchig',
                                                                dates: [
                                                                    '2026-02-17',
                                                                    '2026-03-03',
                                                                    '2026-04-14',
                                                                ],
                                                            },
                                                        ],
                                                        markers: [],
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                },
                manual_timetable: {
                    title: 'Manueller Stundenplan',
                    sections: [
                        {
                            key: 'proposed',
                            items: [course],
                        },
                    ],
                },
            },
            $route: {
                path: '/students-timetables/overview',
                query: {
                    automatic_timetable: 'criteria',
                    automatic_timetable_courses: ['course-1'],
                    automatic_timetable_criteria: ['saturday_free'],
                },
            },
            $router: {
                push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
            manualCourseKey: methods.manualCourseKey,
            manualCourseGroups: methods.manualCourseGroups,
            manualCourseGroupKey: methods.manualCourseGroupKey,
            applyPublishedTimetableSelection: methods.applyPublishedTimetableSelection,
            applySavedTimetableSelection: methods.applySavedTimetableSelection,
            publishedTimetableCellCourses: methods.publishedTimetableCellCourses,
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableTitle() {
                return computed.manualTimetableTitle.call(ctx)
            },
            get publishedTimetableSelection() {
                return computed.publishedTimetableSelection.call(ctx)
            },
            get hasPublishedTimetable() {
                return computed.hasPublishedTimetable.call(ctx)
            },
            get personalTimetableSelection() {
                return computed.personalTimetableSelection.call(ctx)
            },
            get hasPersonalTimetable() {
                return computed.hasPersonalTimetable.call(ctx)
            },
            get activeSavedTimetableSelection() {
                return computed.activeSavedTimetableSelection.call(ctx)
            },
            get activeSavedTimetableCourseGroupKeys() {
                return computed.activeSavedTimetableCourseGroupKeys.call(ctx)
            },
            get publishedTimetableCourseGroupKeys() {
                return computed.publishedTimetableCourseGroupKeys.call(ctx)
            },
            get publishedTimetablePayload() {
                return computed.publishedTimetablePayload.call(ctx)
            },
            get publishedTimetableVisible() {
                return computed.publishedTimetableVisible.call(ctx)
            },
            get personalTimetableEmptyVisible() {
                return computed.personalTimetableEmptyVisible.call(ctx)
            },
            get publishedTimetableSubtitle() {
                return computed.publishedTimetableSubtitle.call(ctx)
            },
            get publishedTimetableWeekdays() {
                return computed.publishedTimetableWeekdays.call(ctx)
            },
            get publishedTimetableSemesters() {
                return computed.publishedTimetableSemesters.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
            get manualSelectedCourses() {
                return computed.manualSelectedCourses.call(ctx)
            },
        }
        const savedCell = ctx.publishedTimetableSemesters[0].weeks[0].hours[0].cells[0]

        methods.openPublishedTimetable.call(ctx)

        expect(ctx.showManualTimetable).toBe(true)
        expect(ctx.showEvaluationSettings).toBe(false)
        expect(ctx.manualTimetableMode).toBe('published')
        expect(ctx.manualTimetableTitle).toBe('Von der Schule gespeicherter Stundenplan')
        expect(ctx.manualSelectedCourseKeys).toEqual(['course-1'])
        expect(ctx.manualSelectedCourseGroupKeys).toEqual(['saved-group'])
        expect(ctx.publishedTimetableVisible).toBe(true)
        expect(ctx.publishedTimetableSubtitle).toBe('5C · ZADRA Isabella · Semester 5 · Schuljahr 2025/26 · 16.06.26, 21:02')
        expect(ctx.publishedTimetableWeekdays).toEqual([{ label: 'Mo' }, { label: 'Di' }])
        expect(methods.publishedTimetableSemesterWeeks(ctx.publishedTimetableSemesters[0])).toHaveLength(1)
        expect(methods.publishedTimetableHourCells.call(ctx, ctx.publishedTimetableSemesters[0].weeks[0].hours[0]))
            .toHaveLength(2)
        expect(methods.publishedTimetableCellClasses.call(ctx, savedCell))
            .toEqual({
                'student-published-timetable__cell--filled': true,
                'student-published-timetable__cell--warning': true,
                'student-published-timetable__cell--conflict': false,
                'student-published-timetable__cell--related': false,
            })
        expect(methods.publishedTimetableSameSlotGroups.call(ctx, ctx.publishedTimetableSemesters[0].weeks[0]))
            .toEqual([
                {
                    key: '10|17:05|17:50|0|D5 - 3R - SHAM|FU · 2-wöchig|2026-02-24,2026-03-10,2026-06-30|E5 - 3R - HÖF|2-wöchig|2026-02-17,2026-03-03,2026-04-14',
                    title: 'Mo 10. 17:05 - 17:50',
                    courses: [
                        {
                            key: 'D5 - 3R - SHAM|FU · 2-wöchig|2026-02-24,2026-03-10,2026-06-30',
                            title: 'D5 - 3R - SHAM',
                            dateRangeLabel: '24.02. - 30.06.',
                            dateLabels: [
                                'Di, 24.02.2026',
                                'Di, 10.03.2026',
                                'Di, 30.06.2026',
                            ],
                        },
                        {
                            key: 'E5 - 3R - HÖF|2-wöchig|2026-02-17,2026-03-03,2026-04-14',
                            title: 'E5 - 3R - HÖF',
                            dateRangeLabel: '17.02. - 14.04.',
                            dateLabels: [
                                'Di, 17.02.2026',
                                'Di, 03.03.2026',
                                'Di, 14.04.2026',
                            ],
                        },
                    ],
                },
            ])
        expect(computed.manualSelectedCourseGroups.call(ctx)).toEqual([savedCourseGroup])
        expect(pushedRoutes).toEqual([
            {
                path: '/students-timetables/overview',
                query: {
                    manual_timetable: 'published',
                },
            },
        ])
    })

    it('opens a personal timetable with the adopted course group selection', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const savedCourseGroup = {
            key: 'saved-group',
            course: 'D1',
            weekday: 1,
            hour: 2,
        }
        const course = {
            key: 'course-1',
            code: 'D1',
            course_groups: [savedCourseGroup],
        }
        const pushedRoutes: unknown[] = []
        const ctx: any = {
            ...methods,
            showManualTimetable: false,
            showEvaluationSettings: true,
            manualTimetableMode: 'manual',
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            overview: {
                personal_timetable: {
                    id: 9,
                    active_course_group_keys: ['saved-group'],
                    timetable: {
                        student: '5C · ZADRA Isabella · Semester 5',
                        weekdays: [{ label: 'Mo' }],
                        semesters: [{ label: 'Semester', weeks: [] }],
                    },
                },
                manual_timetable: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [course],
                        },
                    ],
                },
            },
            $route: {
                path: '/students-timetables/overview',
                query: {},
            },
            $router: {
                push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
            manualCourseKey: methods.manualCourseKey,
            manualCourseGroups: methods.manualCourseGroups,
            manualCourseGroupKey: methods.manualCourseGroupKey,
            applySavedTimetableSelection: methods.applySavedTimetableSelection,
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableTitle() {
                return computed.manualTimetableTitle.call(ctx)
            },
            get personalTimetableSelection() {
                return computed.personalTimetableSelection.call(ctx)
            },
            get hasPersonalTimetable() {
                return computed.hasPersonalTimetable.call(ctx)
            },
            get activeSavedTimetableSelection() {
                return computed.activeSavedTimetableSelection.call(ctx)
            },
            get activeSavedTimetableCourseGroupKeys() {
                return computed.activeSavedTimetableCourseGroupKeys.call(ctx)
            },
            get publishedTimetablePayload() {
                return computed.publishedTimetablePayload.call(ctx)
            },
            get publishedTimetableVisible() {
                return computed.publishedTimetableVisible.call(ctx)
            },
            get personalTimetableEmptyVisible() {
                return computed.personalTimetableEmptyVisible.call(ctx)
            },
            get publishedTimetableSemesters() {
                return computed.publishedTimetableSemesters.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
        }

        methods.openPersonalTimetable.call(ctx)

        expect(ctx.showManualTimetable).toBe(true)
        expect(ctx.showEvaluationSettings).toBe(false)
        expect(ctx.manualTimetableMode).toBe('personal')
        expect(ctx.manualTimetableTitle).toBe('Mein Stundenplan')
        expect(ctx.manualSelectedCourseKeys).toEqual(['course-1'])
        expect(ctx.manualSelectedCourseGroupKeys).toEqual(['saved-group'])
        expect(ctx.publishedTimetableVisible).toBe(true)
        expect(ctx.personalTimetableEmptyVisible).toBe(false)
        expect(pushedRoutes).toEqual([
            {
                path: '/students-timetables/overview',
                query: {
                    manual_timetable: 'personal',
                },
            },
        ])
    })

    it('adopts the published timetable and shows that the view changed to the personal timetable', async () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const savedCourseGroup = {
            key: 'saved-group',
            course: 'D1',
            weekday: 1,
            hour: 2,
        }
        const course = {
            key: 'course-1',
            code: 'D1',
            course_groups: [savedCourseGroup],
        }
        const pushedRoutes: unknown[] = []
        const ctx: any = {
            ...methods,
            showManualTimetable: true,
            showEvaluationSettings: false,
            manualTimetableMode: 'published',
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            personalTimetableSaving: false,
            personalTimetableViewChangedMessageVisible: false,
            overview: {
                published_timetable: {
                    id: 7,
                    active_course_group_keys: ['saved-group'],
                    timetable: {
                        student: '5C · ZADRA Isabella · Semester 5',
                        weekdays: [{ label: 'Mo' }],
                        semesters: [{ label: 'Semester', weeks: [] }],
                    },
                },
                personal_timetable: null,
                manual_timetable: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [course],
                        },
                    ],
                },
            },
            $route: {
                path: '/students-timetables/overview',
                query: {
                    manual_timetable: 'published',
                },
            },
            $router: {
                push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
            manualCourseKey: methods.manualCourseKey,
            manualCourseGroups: methods.manualCourseGroups,
            manualCourseGroupKey: methods.manualCourseGroupKey,
            applySavedTimetableSelection: methods.applySavedTimetableSelection,
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableTitle() {
                return computed.manualTimetableTitle.call(ctx)
            },
            get publishedTimetableSelection() {
                return computed.publishedTimetableSelection.call(ctx)
            },
            get personalTimetableSelection() {
                return computed.personalTimetableSelection.call(ctx)
            },
            get hasPublishedTimetable() {
                return computed.hasPublishedTimetable.call(ctx)
            },
            get activeSavedTimetableSelection() {
                return computed.activeSavedTimetableSelection.call(ctx)
            },
            get activeSavedTimetableCourseGroupKeys() {
                return computed.activeSavedTimetableCourseGroupKeys.call(ctx)
            },
            get publishedTimetablePayload() {
                return computed.publishedTimetablePayload.call(ctx)
            },
            get publishedTimetableVisible() {
                return computed.publishedTimetableVisible.call(ctx)
            },
            get personalTimetableViewChangeVisible() {
                return computed.personalTimetableViewChangeVisible.call(ctx)
            },
            get publishedTimetableSemesters() {
                return computed.publishedTimetableSemesters.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
        }
        const adoptPublishedTimetable = vi.fn(async () => {
            ctx.overview.personal_timetable = {
                id: 9,
                active_course_group_keys: ['saved-group'],
                timetable: ctx.overview.published_timetable.timetable,
            }

            return true
        })
        ctx.studentTimetablesStore = { adoptPublishedTimetable }

        await methods.adoptPublishedTimetable.call(ctx)

        expect(adoptPublishedTimetable).toHaveBeenCalledOnce()
        expect(ctx.showManualTimetable).toBe(true)
        expect(ctx.showEvaluationSettings).toBe(false)
        expect(ctx.manualTimetableMode).toBe('personal')
        expect(ctx.manualTimetableTitle).toBe('Mein Stundenplan')
        expect(ctx.manualSelectedCourseKeys).toEqual(['course-1'])
        expect(ctx.manualSelectedCourseGroupKeys).toEqual(['saved-group'])
        expect(ctx.personalTimetableViewChangedMessageVisible).toBe(true)
        expect(ctx.personalTimetableViewChangeVisible).toBe(true)
        expect(ctx.personalTimetableSaving).toBe(false)
        methods.dismissPersonalTimetableViewChangeMessage.call(ctx)
        expect(ctx.personalTimetableViewChangedMessageVisible).toBe(false)
        expect(ctx.personalTimetableViewChangeVisible).toBe(false)
        expect(pushedRoutes).toEqual([
            {
                path: '/students-timetables/overview',
                query: {
                    manual_timetable: 'personal',
                },
            },
        ])
    })

    it('opens an empty personal timetable when no personal timetable exists yet', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const pushedRoutes: unknown[] = []
        const ctx: any = {
            ...methods,
            showManualTimetable: false,
            showEvaluationSettings: true,
            manualTimetableMode: 'manual',
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            overview: {
                personal_timetable: null,
                manual_timetable: {
                    sections: [],
                },
            },
            $route: {
                path: '/students-timetables/overview',
                query: {},
            },
            $router: {
                push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
            ensureManualTimetableDefaultSelection: methods.ensureManualTimetableDefaultSelection,
            applySavedTimetableSelection: methods.applySavedTimetableSelection,
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableTitle() {
                return computed.manualTimetableTitle.call(ctx)
            },
            get personalTimetableSelection() {
                return computed.personalTimetableSelection.call(ctx)
            },
            get hasPersonalTimetable() {
                return computed.hasPersonalTimetable.call(ctx)
            },
            get activeSavedTimetableSelection() {
                return computed.activeSavedTimetableSelection.call(ctx)
            },
            get activeSavedTimetableCourseGroupKeys() {
                return computed.activeSavedTimetableCourseGroupKeys.call(ctx)
            },
            get publishedTimetablePayload() {
                return computed.publishedTimetablePayload.call(ctx)
            },
            get publishedTimetableVisible() {
                return computed.publishedTimetableVisible.call(ctx)
            },
            get personalTimetableEmptyVisible() {
                return computed.personalTimetableEmptyVisible.call(ctx)
            },
            get publishedTimetableSemesters() {
                return computed.publishedTimetableSemesters.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
        }

        methods.openPersonalTimetable.call(ctx)

        expect(ctx.showManualTimetable).toBe(true)
        expect(ctx.showEvaluationSettings).toBe(false)
        expect(ctx.manualTimetableMode).toBe('personal')
        expect(ctx.manualTimetableTitle).toBe('Mein Stundenplan')
        expect(ctx.hasPersonalTimetable).toBe(false)
        expect(ctx.publishedTimetableVisible).toBe(false)
        expect(ctx.personalTimetableEmptyVisible).toBe(true)
        expect(pushedRoutes).toEqual([
            {
                path: '/students-timetables/overview',
                query: {
                    manual_timetable: 'personal',
                },
            },
        ])
    })

    it('builds manual timetable cells from selected student courses', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const courseGroup = {
            key: 'group-1',
            course: 'D1',
            display_label: 'D1 - 4A - MUE',
            weekday: 1,
            hour: 11,
            time_from: '16:10',
            time_until: '16:55',
            teacher: 'MUE',
            rooms: ['101'],
        }
        const course = {
            key: 'course-1',
            code: 'D1',
            name: 'Deutsch',
            course_groups: [courseGroup],
        }
        const ctx = {
            manualSelectedCourseKeys: ['course-1'],
            overview: {
                school_hours: [],
                manual_timetable: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [course],
                        },
                    ],
                },
            },
            manualCourseKey: methods.manualCourseKey,
            manualCourseGroups: methods.manualCourseGroups,
            manualCourseGroupKey: methods.manualCourseGroupKey,
            manualTimetableHourTimeFrom: methods.manualTimetableHourTimeFrom,
            manualTimetableHourTimeUntil: methods.manualTimetableHourTimeUntil,
            configuredSchoolHour: methods.configuredSchoolHour,
            formatTimeValue: methods.formatTimeValue,
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
            get manualSelectedCourses() {
                return computed.manualSelectedCourses.call(ctx)
            },
            get manualSelectedCourseGroups() {
                return computed.manualSelectedCourseGroups.call(ctx)
            },
        }

        expect(computed.manualSelectedCourseGroups.call(ctx)).toEqual([courseGroup])
        expect(computed.manualTimetableHours.call(ctx)).toEqual([
            {
                value: 11,
                hourLabel: '11.',
                timeFrom: '16:10',
                timeUntil: '16:55',
            },
        ])
        expect(methods.manualGroupsForCell.call(ctx, 1, 11)).toEqual([courseGroup])
        expect(methods.manualCourseGroupDetails(courseGroup)).toBe('D1 - 4A - MUE · MUE · 101')
    })

    it('builds the personal timetable course selection chips from the saved timetable courses', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const ctx: any = {
            ...methods,
            manualTimetableMode: 'personal',
            hiddenSavedTimetableCourseChipKeys: [],
            selectedStudentCoursePickerTab: 'missing',
            selectedStudentCoursePickerMenuKey: '',
            studentCoursePickerDialogOpen: false,
            overview: {
                personal_timetable: {
                    id: 9,
                    timetable: {
                        weekdays: [
                            { label: 'Mo' },
                            { label: 'Di' },
                            { label: 'Do' },
                        ],
                        semesters: [
                            {
                                label: 'Semester',
                                date_range: '16.02.2026 - 10.07.2026',
                                weeks: [
                                    {
                                        hours: [
                                            {
                                                hour: 1,
                                                from: '19:30',
                                                until: '21:10',
                                                cells: [
                                                    {},
                                                    {
                                                        status: 'warning',
                                                        courses: [
                                                            {
                                                                label: 'E5 - 3R - HÖF',
                                                                details: '1w, 2w',
                                                            },
                                                            {
                                                                label: 'D5 - 3R - SHAM',
                                                                details: 'FU · 1w, 2w',
                                                                student_course_type: 'missing',
                                                                student_course_badge: 'Fehlend',
                                                            },
                                                        ],
                                                    },
                                                    {},
                                                ],
                                            },
                                            {
                                                hour: 13,
                                                from: '19:30',
                                                until: '20:15',
                                                cells: [
                                                    {},
                                                    {
                                                        status: 'filled',
                                                        courses: [
                                                            {
                                                                label: 'CH1 - 4F - KOW',
                                                                details: '1w, 2w',
                                                            },
                                                        ],
                                                    },
                                                    {},
                                                ],
                                            },
                                            {
                                                hour: 14,
                                                from: '20:25',
                                                until: '21:10',
                                                cells: [
                                                    {
                                                        status: 'filled',
                                                        courses: [
                                                            {
                                                                label: 'BU2 - 5CK - FUCH',
                                                                details: '1w',
                                                                student_course_type: 'missing',
                                                                student_course_badge: 'Fehlend',
                                                            },
                                                        ],
                                                    },
                                                    {
                                                        status: 'filled',
                                                        courses: [
                                                            {
                                                                label: 'CH1 - 4F - KOW',
                                                                details: '1w, 2w',
                                                            },
                                                        ],
                                                    },
                                                    {},
                                                ],
                                            },
                                            {
                                                hour: 15,
                                                from: '21:10',
                                                until: '21:55',
                                                cells: [
                                                    {
                                                        status: 'filled',
                                                        courses: [
                                                            {
                                                                label: 'BU2 - 5CK - FUCH',
                                                                details: '1w',
                                                                student_course_type: 'missing',
                                                                student_course_badge: 'Fehlend',
                                                            },
                                                        ],
                                                    },
                                                    {},
                                                    {},
                                                ],
                                            },
                                            {
                                                hour: 16,
                                                from: '20:25',
                                                until: '21:55',
                                                cells: [
                                                    {},
                                                    {},
                                                    {
                                                        status: 'filled',
                                                        courses: [
                                                            {
                                                                label: 'E5 - 3R - HÖF',
                                                                details: '1w, 2w',
                                                            },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                },
                manual_timetable: {
                    sections: [
                        {
                            key: 'missing',
                            color: 'error',
                            items: [
                                {
                                    key: 'bu2',
                                    code: 'BU2',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'bu2-14',
                                            display_label: 'BU2 - 5CK - FUCH',
                                            semester: 1,
                                            weekday: 1,
                                            time_from: '20:25',
                                            time_until: '21:10',
                                            recurrence_label: '1w',
                                            dates: ['2026-02-24', '2026-03-10'],
                                        },
                                        {
                                            key: 'bu2-15',
                                            display_label: 'BU2 - 5CK - FUCH',
                                            semester: 1,
                                            weekday: 1,
                                            time_from: '21:10',
                                            time_until: '21:55',
                                            recurrence_label: '1w',
                                            dates: ['2026-02-24', '2026-03-10'],
                                        },
                                    ],
                                },
                                {
                                    key: 'bu3',
                                    code: 'BU3',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'bu3-14',
                                            display_label: 'BU3 - 5CK - TEST',
                                            semester: 1,
                                            weekday: 1,
                                            time_from: '20:25',
                                            time_until: '21:10',
                                            recurrence_label: '1w',
                                            dates: ['2026-03-10'],
                                        },
                                    ],
                                },
                                {
                                    key: 'bu4',
                                    code: 'BU4',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'bu4-14',
                                            display_label: 'BU4 - 5CK - FREE',
                                            semester: 1,
                                            weekday: 1,
                                            time_from: '20:25',
                                            time_until: '21:10',
                                            recurrence_label: '1w',
                                            dates: ['2026-03-17'],
                                        },
                                    ],
                                },
                                {
                                    key: 'd5',
                                    code: 'D5',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'd5-1',
                                            display_label: 'D5 - 3R - SHAM',
                                            weekday: 2,
                                            time_from: '19:30',
                                            time_until: '21:10',
                                            recurrence_label: '1w, 2w',
                                        },
                                    ],
                                },
                            ],
                        },
                        {
                            key: 'proposed',
                            color: 'primary',
                            items: [
                                {
                                    key: 'ch1',
                                    code: 'CH1',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'ch1-13',
                                            display_label: 'CH1 - 4F - KOW',
                                            weekday: 2,
                                            time_from: '19:30',
                                            time_until: '20:15',
                                            recurrence_label: '1w, 2w',
                                        },
                                        {
                                            key: 'ch1-14',
                                            display_label: 'CH1 - 4F - KOW',
                                            weekday: 2,
                                            time_from: '20:25',
                                            time_until: '21:10',
                                            recurrence_label: '1w, 2w',
                                        },
                                    ],
                                },
                                {
                                    key: 'e5',
                                    code: 'E5',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'e5-1',
                                            display_label: 'E5 - 3R - HÖF',
                                            weekday: 2,
                                            time_from: '19:30',
                                            time_until: '21:10',
                                            recurrence_label: '1w, 2w',
                                        },
                                        {
                                            key: 'e5-16',
                                            display_label: 'E5 - 3R - HÖF',
                                            weekday: 4,
                                            time_from: '20:25',
                                            time_until: '21:55',
                                            recurrence_label: '1w, 2w',
                                        },
                                    ],
                                },
                            ],
                        },
                        {
                            key: 'additional',
                            color: 'warning',
                            items: [
                                {
                                    key: 'ph2',
                                    code: 'PH2',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'ph2-1',
                                            display_label: 'PH2 - 6A - ALT',
                                            weekday: 4,
                                            time_from: '18:45',
                                            time_until: '20:15',
                                            recurrence_label: '1w',
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            },
            get personalTimetableSelection() {
                return computed.personalTimetableSelection.call(ctx)
            },
            get activeSavedTimetableSelection() {
                return computed.activeSavedTimetableSelection.call(ctx)
            },
            get publishedTimetablePayload() {
                return computed.publishedTimetablePayload.call(ctx)
            },
            get publishedTimetableWeekdays() {
                return computed.publishedTimetableWeekdays.call(ctx)
            },
            get publishedTimetableSemesters() {
                return computed.publishedTimetableSemesters.call(ctx)
            },
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get studentCoursePickerAllCourseGroups() {
                return computed.studentCoursePickerAllCourseGroups.call(ctx)
            },
            get studentCoursePickerAllEntries() {
                return computed.studentCoursePickerAllEntries.call(ctx)
            },
            get savedTimetableCourseChipEntries() {
                return computed.savedTimetableCourseChipEntries.call(ctx)
            },
            get savedTimetableCourseChips() {
                return computed.savedTimetableCourseChips.call(ctx)
            },
            get studentCoursePickerTabs() {
                return computed.studentCoursePickerTabs.call(ctx)
            },
            get studentCoursePickerSemesterContexts() {
                return computed.studentCoursePickerSemesterContexts.call(ctx)
            },
            get studentCoursePickerCourseGroups() {
                return computed.studentCoursePickerCourseGroups.call(ctx)
            },
            get studentCoursePickerCourseMenus() {
                return computed.studentCoursePickerCourseMenus.call(ctx)
            },
            get selectedStudentCoursePickerMenu() {
                return computed.selectedStudentCoursePickerMenu.call(ctx)
            },
            get selectedStudentCoursePickerEntryOptions() {
                return computed.selectedStudentCoursePickerEntryOptions.call(ctx)
            },
            get personalTimetableCourseSelectionVisible() {
                return computed.personalTimetableCourseSelectionVisible.call(ctx)
            },
        }

        expect(ctx.personalTimetableCourseSelectionVisible).toBe(true)
        expect(ctx.savedTimetableCourseChips).toEqual([
            expect.objectContaining({
                key: 'BU2 - 5CK - FUCH',
                label: 'BU2 - 5CK - FUCH · Mo 14.-15 (1w)',
                hasOverlap: false,
                studentCourseType: 'missing',
                studentCourseBadge: 'Fehlend',
            }),
            expect.objectContaining({
                key: 'CH1 - 4F - KOW',
                label: 'CH1 - 4F - KOW · Di 13.-14 (1w, 2w)',
                hasOverlap: false,
            }),
            expect.objectContaining({
                key: 'D5 - 3R - SHAM',
                label: 'D5 - 3R - SHAM · Di 1. (1w, 2w)',
                hasOverlap: true,
                studentCourseType: 'missing',
                studentCourseBadge: 'Fehlend',
            }),
            expect.objectContaining({
                key: 'E5 - 3R - HÖF',
                label: 'E5 - 3R - HÖF · Di 1., Do 16. (1w, 2w)',
                hasOverlap: true,
            }),
        ])
        expect(methods.savedTimetableCourseChipBadgeLabel(ctx.savedTimetableCourseChips[0])).toBe('F')
        expect(ctx.studentCoursePickerTabs).toEqual([
            { value: 'missing', label: 'Fehlende Kurse' },
            { value: 'proposed', label: 'Vorgesehene Kurse' },
            { value: 'additional', label: 'Zusätzliche Kurse' },
            { value: 'open', label: 'Offene Kurse' },
            { value: 'all', label: 'Alle' },
        ])
        expect(ctx.studentCoursePickerSemesterContexts).toEqual([
            {
                value: 1,
                label: 'Semester',
                dateRangeLabel: '16.02.2026 - 10.07.2026',
            },
        ])
        expect(ctx.studentCoursePickerCourseMenus.map(courseMenu => courseMenu.label)).toEqual(['BU', 'D'])
        expect(ctx.selectedStudentCoursePickerMenu).toBeNull()
        expect(ctx.selectedStudentCoursePickerEntryOptions).toEqual([])

        methods.selectStudentCoursePickerMenu.call(ctx, ctx.studentCoursePickerCourseMenus[0])

        expect(ctx.selectedStudentCoursePickerMenu).toEqual(expect.objectContaining({
            label: 'BU',
            semesterLabel: 'Semester',
            semesterDateRangeLabel: '16.02.2026 - 10.07.2026',
        }))
        expect(ctx.selectedStudentCoursePickerEntryOptions).toEqual([
            expect.objectContaining({
                label: 'BU2 - 5CK - FUCH',
                scheduleLabel: 'Mo 20:25 - 21:55 (1w)',
                isActive: true,
                isDisabled: false,
                color: 'success',
            }),
            expect.objectContaining({
                label: 'BU3 - 5CK - TEST',
                scheduleLabel: 'Mo 20:25 - 21:10 (1w)',
                hasBlockingOverlap: true,
                hasRelatedOverlap: false,
                isActive: false,
                isDisabled: true,
                color: 'error',
            }),
            expect.objectContaining({
                label: 'BU4 - 5CK - FREE',
                scheduleLabel: 'Mo 20:25 - 21:10 (1w)',
                hasBlockingOverlap: false,
                hasRelatedOverlap: true,
                isActive: false,
                isDisabled: false,
                color: 'warning',
            }),
        ])
        expect(methods.studentCoursePickerMenuHasActiveSelection.call(ctx, ctx.selectedStudentCoursePickerMenu)).toBe(true)

        methods.toggleStudentCoursePickerEntry.call(ctx, ctx.selectedStudentCoursePickerEntryOptions[1])

        expect(ctx.hiddenSavedTimetableCourseChipKeys).toEqual([])

        expect(methods.publishedTimetableCellCourses.call(ctx, {
            courses: [
                { label: 'BU2 - 5CK - FUCH' },
                { label: 'E5 - 3R - HÖF' },
            ],
        })).toHaveLength(2)

        methods.deselectSavedTimetableCourseChip.call(ctx, ctx.savedTimetableCourseChips[0])

        expect(ctx.hiddenSavedTimetableCourseChipKeys).toEqual(['BU2 - 5CK - FUCH'])
        expect(methods.publishedTimetableCellCourses.call(ctx, {
            courses: [
                { label: 'BU2 - 5CK - FUCH' },
                { label: 'E5 - 3R - HÖF' },
            ],
        })).toEqual([{ label: 'E5 - 3R - HÖF' }])

        methods.deselectAllSavedTimetableCourseChips.call(ctx)

        expect(ctx.hiddenSavedTimetableCourseChipKeys).toEqual(['BU2 - 5CK - FUCH', 'CH1 - 4F - KOW', 'D5 - 3R - SHAM', 'E5 - 3R - HÖF'])
        expect(ctx.savedTimetableCourseChips).toEqual([])
        expect(ctx.personalTimetableCourseSelectionVisible).toBe(true)

        ctx.selectedStudentCoursePickerTab = 'open'

        expect(ctx.studentCoursePickerCourseMenus.map(courseMenu => courseMenu.label)).toEqual(['BU', 'PH'])

        ctx.selectedStudentCoursePickerTab = 'all'

        expect(ctx.studentCoursePickerCourseMenus.map(courseMenu => courseMenu.label)).toEqual(['BU', 'CH', 'D', 'E', 'PH'])
        expect(ctx.selectedStudentCoursePickerEntryOptions[0]).toEqual(expect.objectContaining({
            courseChipKey: 'BU2 - 5CK - FUCH',
            isActive: false,
        }))

        methods.toggleStudentCoursePickerEntry.call(ctx, ctx.selectedStudentCoursePickerEntryOptions[0])

        expect(ctx.hiddenSavedTimetableCourseChipKeys).toEqual(['CH1 - 4F - KOW', 'D5 - 3R - SHAM', 'E5 - 3R - HÖF'])
    })

    it('keeps manual timetable course selection helpers for the editable manual preview', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const savedCourseGroup = {
            key: 'group-1',
            course: 'D5',
            display_label: 'D5 - 3R - SHAM',
            weekday: 2,
            hour: 14,
            time_from: '20:25',
            time_until: '21:55',
            recurrence_label: '1w, 2w',
        }
        const course = {
            key: 'course-1',
            code: 'D5',
            course_groups: [savedCourseGroup],
        }
        const ctx: any = {
            ...methods,
            manualTimetableMode: 'manual',
            manualSelectedCourseKeys: ['course-1'],
            manualSelectedCourseGroupKeys: ['group-1'],
            overview: {
                manual_timetable: {
                    sections: [
                        {
                            key: 'missing',
                            items: [course],
                        },
                    ],
                },
            },
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
            get manualSelectedCourses() {
                return computed.manualSelectedCourses.call(ctx)
            },
            get manualSelectedCourseGroups() {
                return computed.manualSelectedCourseGroups.call(ctx)
            },
        }

        expect(methods.manualCourseGroupChipLabel.call(ctx, savedCourseGroup))
            .toBe('D5 - 3R - SHAM · Di 20:25 - 21:55 (1w, 2w)')
        expect(methods.manualCourseGroupStudentCourseBadge.call(ctx, savedCourseGroup)).toBe('Fehlend')
    })

    it('shows the imported student religion on the religion card', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain('class="overview-selected-card__meta"')
        expect(source).toContain('this.overview?.selection_items')
        expect(source).toContain('this.overview?.course_sections')
        expect(source).toContain('this.overview?.selection_options?.[optionKey]')
        expect(source).toContain('icon="mdi-pencil"')
        expect(source).toContain('@click="openSelectionDialog(item)"')
        expect(source).toContain('restoreSelectionDefaults')
        expect(source).toContain('selectionOverridePayload()')
        expect(source).toContain('this.studentTimetablesStore.updateProfileSelection')
        expect(source).toContain('this.studentTimetablesStore.restoreProfileSelection')
        expect(source).toContain('this.overview?.selection_override')
        expect(source).not.toContain('localStorage')
        expect(source).not.toContain('studentReligionMeta(religion)')
    })

    it('uses a back action instead of a reset action', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('prepend-icon="mdi-arrow-left"')
        expect(source).toContain('@click="handleBack"')
        expect(source).toContain("this.$emit('close')")
        expect(source).toContain('Zurück')
        expect(source).toContain('student-evaluation-settings__close-button')
        expect(source).toContain('prepend-icon="mdi-arrow-left"')
        expect(source).toContain('student-result-course-panels')
        expect(source).toContain('v-if="currentStep === \'courses\'"')
        expect(source).toContain('v-else-if="currentStep === \'result\'" class="student-generated-timetable"')
        expect(source).toContain('student-generated-timetable__back-button')
        expect(source).toContain('student-evaluation-settings__automatic-card')
        expect(source).toContain('color="primary"')
        expect(source).toContain('color="success"')
        expect(source).toContain('readOnlySelectedCourseSections')
        expect(source).toContain('v-for="section in regularCourseSections"')
        expect(source).toContain('additionalCoursePanelVisible')
        expect(source).toContain('.student-evaluation-settings__course-section-card--additional')
        expect(source).toContain('background: #fed7aa')
        expect(source).toContain('border-color: rgba(234, 88, 12, 0.28)')
        expect(source).toContain('setAdditionalCourseSelected(course, $event)')
        expect(source).toContain(':disabled="additionalCourseSelectionLocked || generatingTimetable"')
        expect(source).toContain('Stundenplan erweitern')
        expect(source).toContain('createExtendedAutomaticTimetable')
        expect(source).toContain(':model-value="true"')
        expect(source).toContain('student-generated-criteria__check')
        expect(source).toContain('student-generated-criteria__status')
        expect(source).toContain("'student-generated-criteria__card--reached': generatedQualityCriterionSelected(criterion) && generatedQualityCriterionReached(criterion)")
        expect(source).toContain("generatedQualityCriterionReached(criterion) ? 'mdi-check-circle' : 'mdi-close-circle'")
        expect(source).toContain('<v-checkbox-btn')
        expect(source).toContain(':model-value="generatedQualityCriterionSelected(criterion)"')
        expect(source).toContain('@update:model-value="toggleGeneratedQualityCriterion(criterion)"')
        expect(source).toContain('disabled')
        expect(source).not.toContain('@click="resetChanges"')
    })

    it('initializes selected course keys from the url state', () => {
        const data = (StudentTimetableEvaluationSettings as any).data
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            initialStep: 'courses',
            initialSelectedCourseKeys: ['course-2'],
            initialSelectedQualityCriterionKeys: ['saturday_free'],
            normalizedStep: methods.normalizedStep,
            normalizedCourseKeys: methods.normalizedCourseKeys,
            normalizedResultBackStep: methods.normalizedResultBackStep,
        }

        expect(data.call(ctx).selectedCourseKeys).toEqual(['course-2'])
        expect(data.call(ctx).selectedQualityCriterionKeys).toEqual(['saturday_free'])
        expect(data.call(ctx).resultBackStep).toBe('courses')
    })

    it('moves from criteria to the result while keeping criteria as the back target', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const proposedCourses = [
            { key: 'course-1', code: 'M1', name: 'Mathematik', semester: 1, hours: 3 },
            { key: 'course-2', code: 'D1', name: 'Deutsch', semester: 1, hours: 2 },
        ]
        const ctx = {
            currentStep: 'criteria',
            proposedCourses,
            selectedCourseKeys: [],
            saveSettings: async () => true,
            ensureDefaultQualityCriterionSelection: () => undefined,
            resetGeneratedTimetableSelection: () => undefined,
            courseSelectionKey: methods.courseSelectionKey,
            courseSelected: methods.courseSelected,
            moveToStep: methods.moveToStep,
            normalizedStep: methods.normalizedStep,
            normalizedResultBackStep: methods.normalizedResultBackStep,
            selectAllProposedCourses: methods.selectAllProposedCourses,
            emitCourseSelectionChange: methods.emitCourseSelectionChange,
            ensureAutomaticTimetableForResultStep: () => undefined,
            resultBackStep: 'criteria',
            get selectedCourses() {
                return proposedCourses.filter(course => methods.courseSelected.call(ctx, course))
            },
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        await methods.continueToNextStep.call(ctx)

        expect(ctx.currentStep).toBe('result')
        expect(ctx.resultBackStep).toBe('criteria')
        expect(ctx.selectedCourseKeys).toEqual(['course-1', 'course-2'])

        methods.setCourseSelected.call(ctx, proposedCourses[1], false)

        expect(ctx.selectedCourseKeys).toEqual(['course-1', 'course-2'])

        expect(emitted).toEqual([
            {
                event: 'course-selection-change',
                payload: ['course-1', 'course-2'],
            },
            {
                event: 'step-change',
                payload: 'result',
            },
        ])
    })

    it('creates an automatic timetable before showing the result step', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'courses',
            selectedCourseKeys: ['course-1'],
            selectedCourses: [{ key: 'course-1' }],
            resultBackStep: 'criteria',
            resetGeneratedTimetableSelection: () => undefined,
            createAutomaticTimetable: async () => {
                ctx.currentStep = 'result'
            },
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        await methods.continueToNextStep.call(ctx)

        expect(ctx.currentStep).toBe('result')
        expect(ctx.resultBackStep).toBe('courses')
        expect(emitted).toEqual([])

        await methods.continueToNextStep.call(ctx)

        expect(emitted).toEqual([
            {
                event: 'courses-selected',
                payload: [{ key: 'course-1' }],
            },
        ])
    })

    it('returns from result to the step that opened it', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'result',
            resultBackStep: 'criteria',
            selectedCourseKeys: [],
            normalizedStep: methods.normalizedStep,
            normalizedResultBackStep: methods.normalizedResultBackStep,
            moveToStep: methods.moveToStep,
            selectAllProposedCourses: () => undefined,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        methods.handleBack.call(ctx)

        expect(ctx.currentStep).toBe('criteria')
        expect(emitted).toEqual([
            {
                event: 'step-change',
                payload: 'criteria',
            },
        ])

        ctx.currentStep = 'result'
        ctx.resultBackStep = 'courses'

        methods.handleBack.call(ctx)

        expect(ctx.currentStep).toBe('courses')
        expect(emitted.at(-1)).toEqual({
            event: 'step-change',
            payload: 'courses',
        })
    })

    it('shows selected regular courses as read-only rows in the result step', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const selectedCourse = { key: 'course-1', code: 'M1' }
        const unselectedCourse = { key: 'course-2', code: 'D1' }
        const additionalCourse = { key: 'course-3', code: 'E2' }
        const ctx = {
            selectedCourseKeys: ['course-1'],
            courseSections: [
                {
                    key: 'missing',
                    title: 'Fehlende Kurse',
                    items: [selectedCourse, unselectedCourse],
                },
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [additionalCourse],
                },
            ],
            courseSelectionKey: methods.courseSelectionKey,
            courseSelectedByDefault: methods.courseSelectedByDefault,
            courseSelected: methods.courseSelected,
            get allCoursesSelectedByDefault() {
                return computed.allCoursesSelectedByDefault.call(ctx)
            },
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get regularCourseSections() {
                return computed.regularCourseSections.call(ctx)
            },
        }

        expect(computed.readOnlySelectedCourseSections.call(ctx)).toEqual([
            {
                key: 'missing',
                title: 'Fehlende Kurse',
                items: [selectedCourse],
            },
        ])
    })

    it('shows additional courses as a selectable result panel after timetable creation', () => {
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            courseSections: [
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [{ key: 'course-3', code: 'E2' }],
                },
            ],
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
            get readOnlySelectedCourseSections() {
                return []
            },
            get additionalCoursePanelVisible() {
                return computed.additionalCoursePanelVisible.call(ctx)
            },
        }

        expect(computed.additionalCoursePanelVisible.call(ctx)).toBe(true)
        expect(computed.resultCoursePanelsVisible.call(ctx)).toBe(true)
        expect(computed.additionalCourses.call(ctx)).toEqual([{ key: 'course-3', code: 'E2' }])
    })

    it('keeps additional courses unchecked when the generated timetable is first shown', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const course = { key: 'course-3', code: 'E2' }
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: [],
            courseSections: [
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [course],
                },
            ],
            courseSelectionKey: methods.courseSelectionKey,
            additionalCourseSelected: methods.additionalCourseSelected,
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
        }

        expect(computed.additionalCoursePanelVisible.call(ctx)).toBe(true)
        expect(methods.additionalCourseSelected.call(ctx, course)).toBe(false)
    })

    it('hides the generated timetable while additional course selection is pending and restores it on back', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const moveToStep = vi.fn()
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: [],
            additionalCourseSelectionChangedAfterTimetable: false,
            courseSelectionKey: methods.courseSelectionKey,
            commitAdditionalCourseSelectionChange: methods.commitAdditionalCourseSelectionChange,
            resetAdditionalCourseSelection: methods.resetAdditionalCourseSelection,
            moveToStep,
        }

        methods.setAdditionalCourseSelected.call(ctx, { key: 'course-3' }, true)

        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(true)

        methods.handleBack.call(ctx)

        expect(ctx.selectedAdditionalCourseKeys).toEqual([])
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
        expect(ctx.generatedTimetable).toEqual({ slots: {} })
        expect(moveToStep).not.toHaveBeenCalled()
    })

    it('locks additional course checkboxes after extending and unlocks them on back', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const moveToStep = vi.fn()
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: ['course-3'],
            additionalCourseSelectionChangedAfterTimetable: true,
            additionalCourseSelectionLocked: false,
            selectedTimetableType: 'green',
            selectedTimetableNumber: 3,
            courseSelectionKey: methods.courseSelectionKey,
            commitAdditionalCourseSelectionChange: methods.commitAdditionalCourseSelectionChange,
            resetGeneratedTimetableSelection: methods.resetGeneratedTimetableSelection,
            moveToStep,
            async createAutomaticTimetable() {
                this.generatedTimetable = { slots: { extended: true } }
                this.additionalCourseSelectionChangedAfterTimetable = false
            },
        }

        await methods.createExtendedAutomaticTimetable.call(ctx)

        expect(ctx.additionalCourseSelectionLocked).toBe(true)
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])
        expect(ctx.selectedTimetableType).toBe(null)
        expect(ctx.selectedTimetableNumber).toBe(1)

        methods.setAdditionalCourseSelected.call(ctx, { key: 'course-3' }, false)

        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])

        methods.handleBack.call(ctx)

        expect(ctx.additionalCourseSelectionLocked).toBe(false)
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(true)
        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])
        expect(moveToStep).not.toHaveBeenCalled()

        methods.setAdditionalCourseSelected.call(ctx, { key: 'course-3' }, false)

        expect(ctx.selectedAdditionalCourseKeys).toEqual([])
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
    })

    it('does not change course selection from the result read-only table', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'result',
            selectedCourseKeys: ['course-1'],
            courseSelectionKey: methods.courseSelectionKey,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        methods.setCourseSelected.call(ctx, { key: 'course-1' }, false)

        expect(ctx.selectedCourseKeys).toEqual(['course-1'])
        expect(emitted).toEqual([])
    })

    it('posts selected course keys to the student automatic timetable endpoint', async () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("axios.post('/api/homepage/students-timetables/automatic-timetable'")
        expect(source).toContain('selected_course_keys: this.selectedCourseKeys')
        expect(source).toContain('selected_additional_course_keys: this.selectedVisibleAdditionalCourseKeys')
        expect(source).toContain('selected_additional_courses_required: this.selectedVisibleAdditionalCourseKeys.length > 0')
        expect(source).toContain('selected_quality_criterion_keys: this.selectedQualityCriterionKeys')
        expect(source).toContain('selected_timetable_type: this.selectedTimetableType')
        expect(source).toContain('selected_timetable_number: this.selectedTimetableNumber')
        expect(source).toContain('selection: this.selectionOverride')
        expect(source).toContain('this.schoolHours = response.data?.data?.school_hours || []')
        expect(source).toContain('selectionOverride')
        expect(source).toContain("this.moveToStep('result')")
        expect(source).toContain('ensureAutomaticTimetableForResultStep()')
        expect(source).toContain('canMoveGeneratedTimetable(-1)')
        expect(source).toContain('canMoveGeneratedTimetable(1)')
        expect(source).toContain('generatedTimetableDisplaySlot(weekday.value, hour.value)')
        expect(source).toContain('generatedTimetableOccasionalMarkers(weekday.value, hour.value)')
        expect(source).toContain('student-generated-timetable__time-range')
        expect(source).toContain('student-generated-timetable__badge')
        expect(source).toContain('generatedSlotWeekMarker(block)')
        expect(source).toContain("'student-generated-timetable__cell--additional': generatedTimetableCellHasAdditionalCourse(weekday.value, hour.value)")
        expect(source).toContain('generatedTimetableCellHasAdditionalCourse(weekday, hour)')
        expect(source).toContain('.student-generated-timetable__cell--additional')
        expect(source).toContain('student-generated-criteria__meta-row')
        expect(source).toContain('student-generated-criteria__label')
        expect(source).toContain('student-generated-criteria__controls')
        expect(source).toContain('activeGeneratedQualityCriteria.length || selectedAdditionalCourseKeys.length')
        expect(source).toContain('student-generated-criteria__card--additional')
        expect(source).toContain('Zusatzkurse')
        expect(source).toContain('generatedAdditionalCourseAcceptanceLabel()')
        expect(source).toContain('generatedAdditionalCourseAcceptanceIcon()')
        expect(source).toContain('Stundenpläne')
    })

    it('hides fully conflicting additional courses from the result panel', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: ['additional-red', 'additional-green'],
            timetableCounts: {
                conflicting_additional_course_keys: ['additional-red'],
            },
            courseSections: [
                {
                    key: 'additional',
                    title: 'ZusÃ¤tzliche Kurse',
                    items: [
                        { key: 'additional-red', code: 'D2' },
                        { key: 'additional-green', code: 'M2' },
                    ],
                },
            ],
            courseSelectionKey: methods.courseSelectionKey,
            normalizedCourseCode: methods.normalizedCourseCode,
            courseCodeAliases: methods.courseCodeAliases,
            courseComparisonKeys: methods.courseComparisonKeys,
            hiddenGeneratedAdditionalCourses: methods.hiddenGeneratedAdditionalCourses,
            generatedAdditionalConflictCourses: methods.generatedAdditionalConflictCourses,
            additionalCourseHiddenForSelectedTimetable: methods.additionalCourseHiddenForSelectedTimetable,
            generatedSlotVisualConflictBlocks: () => [],
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
            get hiddenGeneratedAdditionalCourseKeySet() {
                return computed.hiddenGeneratedAdditionalCourseKeySet.call(ctx)
            },
        }

        expect(computed.additionalCourses.call(ctx)).toEqual([
            { key: 'additional-green', code: 'M2' },
        ])
        expect(computed.selectedVisibleAdditionalCourseKeys.call(ctx)).toEqual(['additional-green'])
    })

    it('shows current timetable criterion status independently from the checkbox selection', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods

        expect(methods.generatedQualityCriterionReached({ selected_reached: true, count: 0 })).toBe(true)
        expect(methods.generatedQualityCriterionReached({ selected_reached: false, count: 8 })).toBe(false)
        expect(methods.generatedQualityCriterionReached({ count: 3 })).toBe(true)
    })

    it('summarizes selected additional courses next to generated criteria', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            selectedAdditionalCourseKeys: ['course-3', 'course-4'],
            generatedTimetable: {
                additionalCoursesAccepted: false,
                acceptedAdditionalCourseCount: 1,
                missingAdditionalCourses: [{ key: 'course-4' }],
            },
            generatedAdditionalCoursesAccepted: methods.generatedAdditionalCoursesAccepted,
            generatedMissingAdditionalCourses: methods.generatedMissingAdditionalCourses,
            generatedAcceptedAdditionalCourseCount: methods.generatedAcceptedAdditionalCourseCount,
        }

        expect(methods.generatedAdditionalCourseAcceptanceLabel.call(ctx)).toBe('1 / 2')
        expect(methods.generatedAdditionalCourseAcceptanceIcon.call(ctx)).toBe('mdi-alert-circle')
        expect(methods.generatedAdditionalCourseAcceptanceColor.call(ctx)).toBe('error')

        ctx.generatedTimetable = {
            additionalCoursesAccepted: true,
            acceptedAdditionalCourseCount: 2,
            missingAdditionalCourses: [],
        }

        expect(methods.generatedAdditionalCourseAcceptanceLabel.call(ctx)).toBe('2 / 2')
        expect(methods.generatedAdditionalCourseAcceptanceIcon.call(ctx)).toBe('mdi-check-circle')
        expect(methods.generatedAdditionalCourseAcceptanceColor.call(ctx)).toBe('success')
    })

    it('toggles generated quality criteria and emits url state', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'result',
            generatingTimetable: false,
            selectedCourseKeys: ['course-1'],
            selectedQualityCriterionKeys: [],
            emitQualityCriteriaSelectionChange: methods.emitQualityCriteriaSelectionChange,
            resetGeneratedTimetableSelection: methods.resetGeneratedTimetableSelection,
            selectedTimetableType: 'green',
            selectedTimetableNumber: 3,
            createAutomaticTimetable: async () => undefined,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        await methods.toggleGeneratedQualityCriterion.call(ctx, { key: 'saturday_free' })

        expect(ctx.selectedQualityCriterionKeys).toEqual(['saturday_free'])
        expect(emitted).toEqual([
            {
                event: 'quality-criteria-selection-change',
                payload: ['saturday_free'],
            },
        ])

        await methods.toggleGeneratedQualityCriterion.call(ctx, { key: 'saturday_free' })

        expect(ctx.selectedQualityCriterionKeys).toEqual([])
        expect(emitted).toEqual([
            {
                event: 'quality-criteria-selection-change',
                payload: ['saturday_free'],
            },
            {
                event: 'quality-criteria-selection-change',
                payload: [],
            },
        ])
    })

    it('defaults the first active quality criterion only when url state is not explicit', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const defaultContext = {
            defaultQualityCriterionSelection: true,
            defaultQualityCriterionSelectionApplied: false,
            selectedQualityCriterionKeys: [],
            criteria: [
                { key: 'saturday_free', enabled: true },
                { key: 'early_end', enabled: true },
            ],
            activeQualityCriterionKeys: methods.activeQualityCriterionKeys,
            emitQualityCriteriaSelectionChange: methods.emitQualityCriteriaSelectionChange,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        methods.ensureDefaultQualityCriterionSelection.call(defaultContext)

        expect(defaultContext.selectedQualityCriterionKeys).toEqual(['saturday_free'])

        const explicitEmptyContext = {
            ...defaultContext,
            defaultQualityCriterionSelection: false,
            defaultQualityCriterionSelectionApplied: false,
            selectedQualityCriterionKeys: [],
        }

        methods.ensureDefaultQualityCriterionSelection.call(explicitEmptyContext)

        expect(explicitEmptyContext.selectedQualityCriterionKeys).toEqual([])
    })

    it('moves within the current generated timetable result bucket with arrow controls', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            generatedTimetable: { type: 'green' },
            selectedTimetableType: 'green',
            selectedTimetableNumber: 2,
            timetableCounts: {
                full_green_timetable_count: 0,
                green_timetable_count: 2,
                conflict_timetable_count: 1,
            },
            generatedTimetableTypeOrder: methods.generatedTimetableTypeOrder,
            generatedTimetableCountForType: methods.generatedTimetableCountForType,
            resolveGeneratedTimetableSelection: methods.resolveGeneratedTimetableSelection,
            get generatedTimetableNavigationType() {
                return computed.generatedTimetableNavigationType.call(ctx)
            },
            get generatedTimetableTotalCount() {
                return computed.generatedTimetableTotalCount.call(ctx)
            },
            get generatedTimetableAbsoluteNumber() {
                return computed.generatedTimetableAbsoluteNumber.call(ctx)
            },
            canMoveGeneratedTimetable: methods.canMoveGeneratedTimetable,
            createAutomaticTimetable: async () => undefined,
        }

        expect(computed.generatedTimetablePositionLabel.call(ctx)).toBe('2 / 2')
        expect(methods.canMoveGeneratedTimetable.call(ctx, 1)).toBe(false)

        await methods.moveGeneratedTimetable.call(ctx, -1)

        expect(ctx.selectedTimetableType).toBe('green')
        expect(ctx.selectedTimetableNumber).toBe(1)
    })

    it('uses the selected criteria timetable count for the generated timetable counter', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            generatedTimetable: { type: 'green' },
            selectedTimetableType: 'green',
            selectedTimetableNumber: 1,
            selectedQualityCriterionKeys: ['saturday_free'],
            timetableCounts: {
                green_timetable_count: 12,
                selected_quality_criteria_count: 5,
            },
            selectedQualityCriteriaTimetableCount: methods.selectedQualityCriteriaTimetableCount,
            generatedTimetableCountForType: methods.generatedTimetableCountForType,
            get generatedTimetableNavigationType() {
                return computed.generatedTimetableNavigationType.call(ctx)
            },
            get generatedTimetableTotalCount() {
                return computed.generatedTimetableTotalCount.call(ctx)
            },
            get generatedTimetableAbsoluteNumber() {
                return computed.generatedTimetableAbsoluteNumber.call(ctx)
            },
        }

        expect(computed.generatedTimetablePositionLabel.call(ctx)).toBe('1 / 5')
    })

    it('renders single date overlaps as markers instead of visual conflicts', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            generatedTimetable: {
                slots: {},
            },
            generatedSlotBlock: methods.generatedSlotBlock,
            generatedSlotBlockIdentity: methods.generatedSlotBlockIdentity,
            generatedSlotBlockIsOccasional: methods.generatedSlotBlockIsOccasional,
            generatedSlotBlocksMatch: methods.generatedSlotBlocksMatch,
            generatedSlotConflictBlocksIncludingRegular: methods.generatedSlotConflictBlocksIncludingRegular,
            generatedSlotDetails: methods.generatedSlotDetails,
            generatedSlotWeekMarker: () => '',
            generatedSlotVisualConflictBlocks: methods.generatedSlotVisualConflictBlocks,
            generatedTimetableSlot: methods.generatedTimetableSlot,
            courseGroupDates: methods.courseGroupDates,
            uniqueGeneratedSlotBlocks: methods.uniqueGeneratedSlotBlocks,
            uniqueGeneratedTimetableOccasionalMarkers: methods.uniqueGeneratedTimetableOccasionalMarkers,
        }
        const singleDateGroup = {
            recurrence_type: 'single',
            dates_count: 1,
            dates: ['2026-02-17'],
            display_label: 'LPT - 1CK - DREI',
        }
        const weeklyGroup = {
            recurrence_type: 'weekly',
            dates_count: 20,
            dates: ['2026-02-17', '2026-02-24'],
            display_label: 'D1 - 1C - GOS',
        }
        const regularSlot = {
            code: 'D1',
            sourceLabel: 'D1-1C-GOS',
            courseGroup: weeklyGroup,
            conflicts: [
                {
                    code: 'LPT',
                    sourceLabel: 'LPT-1CK-DREI',
                    courseGroup: singleDateGroup,
                    isOccasional: true,
                },
            ],
        }
        const occasionalSlot = {
            code: 'LPT',
            sourceLabel: 'LPT-1CK-DREI',
            courseGroup: singleDateGroup,
            isOccasional: true,
            conflicts: [
                {
                    code: 'M1',
                    sourceLabel: 'M1-1C-MAY',
                    courseGroup: weeklyGroup,
                },
            ],
        }

        ctx.generatedTimetable.slots = {
            '3-14': occasionalSlot,
        }

        expect(methods.generatedSlotVisualConflictBlocks.call(ctx, regularSlot)).toEqual([])
        expect(methods.generatedSlotOccasionalConflictMarkers.call(ctx, regularSlot)).toMatchObject([
            {
                code: 'LPT',
                date: '2026-02-17',
            },
        ])
        expect(methods.generatedTimetableDisplaySlot.call(ctx, 3, 14)).toMatchObject({
            code: 'M1',
        })
    })

    it('shows hour times and generated slot badges in the timetable result', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            generatedTimetable: {
                slots: {
                    '1-5': {
                        key: 'slot-1',
                        code: 'M6',
                        sourceLabel: 'M6A',
                        isDistanceLearningCourse: true,
                        courseGroup: {
                            hour: 5,
                            time_from: '11:10:00',
                            time_until: '11:55:00',
                            recurrence_interval: 2,
                        },
                    },
                },
            },
            generatedTimetableSchoolHour: methods.generatedTimetableSchoolHour,
            generatedTimetableConfiguredSchoolHour: methods.generatedTimetableConfiguredSchoolHour,
            generatedTimetableHourTimeFrom: methods.generatedTimetableHourTimeFrom,
            generatedTimetableHourTimeUntil: methods.generatedTimetableHourTimeUntil,
            formatTimeValue: methods.formatTimeValue,
            courseGroupWeekMarkerValue: methods.courseGroupWeekMarkerValue,
            courseGroupWeekInterval: methods.courseGroupWeekInterval,
            courseGroupDates: methods.courseGroupDates,
            weekIntervalFromDates: methods.weekIntervalFromDates,
            dateFromIsoValue: methods.dateFromIsoValue,
            generatedSlotWeekMarker: methods.generatedSlotWeekMarker,
            visibleTimetableEntryWeekMarker: methods.visibleTimetableEntryWeekMarker,
            timetableEntryWeekMarkers: methods.timetableEntryWeekMarkers,
            timetableWeekMarkerEntries: methods.timetableWeekMarkerEntries,
            timetableEntriesShareWeekMarkerContext: methods.timetableEntriesShareWeekMarkerContext,
            timetableEntryCourseContextKey: methods.timetableEntryCourseContextKey,
        }

        expect(computed.generatedTimetableHours.call(ctx)).toEqual([
            {
                value: 5,
                hourLabel: '5.',
                timeFrom: '11:10',
                timeUntil: '11:55',
            },
        ])
        expect(methods.generatedSlotWeekMarker.call(ctx, ctx.generatedTimetable.slots['1-5'])).toBe('2-w')
        expect(ctx.generatedTimetable.slots['1-5'].isDistanceLearningCourse).toBe(true)
    })

    it('marks generated timetable cells with additional courses', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            generatedTimetable: {
                slots: {
                    '1-5': {
                        key: 'slot-1',
                        code: 'INF2',
                        isAdditionalCourse: true,
                    },
                },
                occasionalAppointments: [
                    {
                        key: 'appointment-1',
                        weekday: 2,
                        hour: 6,
                        code: 'INF2',
                        isAdditionalCourse: true,
                    },
                ],
            },
            generatedTimetableDisplaySlot: methods.generatedTimetableDisplaySlot,
            generatedTimetableSlot: methods.generatedTimetableSlot,
            generatedSlotBlock: methods.generatedSlotBlock,
            generatedSlotDisplayBlocks: methods.generatedSlotDisplayBlocks,
            generatedSlotVisualConflictBlocks: methods.generatedSlotVisualConflictBlocks,
            generatedSlotConflictBlocksIncludingRegular: methods.generatedSlotConflictBlocksIncludingRegular,
            generatedSlotBlockIsOccasional: methods.generatedSlotBlockIsOccasional,
            generatedSlotBlocks: methods.generatedSlotBlocks,
            generatedSlotBlockIdentity: methods.generatedSlotBlockIdentity,
            generatedSlotBlocksMatch: methods.generatedSlotBlocksMatch,
            uniqueGeneratedSlotBlocks: methods.uniqueGeneratedSlotBlocks,
            generatedSlotDetails: methods.generatedSlotDetails,
            generatedTimetableOccasionalMarkers: methods.generatedTimetableOccasionalMarkers,
            occasionalAppointmentSelectedForGeneratedTimetable: methods.occasionalAppointmentSelectedForGeneratedTimetable,
            courseGroupDates: () => [],
            generatedAppointmentWeekMarker: () => '',
            generatedSlotOccasionalConflictMarkers: () => [],
            uniqueGeneratedTimetableOccasionalMarkers: methods.uniqueGeneratedTimetableOccasionalMarkers,
        }

        expect(methods.generatedTimetableCellHasAdditionalCourse.call(ctx, 1, 5)).toBe(true)
        expect(methods.generatedTimetableCellHasAdditionalCourse.call(ctx, 2, 6)).toBe(true)
        expect(methods.generatedTimetableCellHasAdditionalCourse.call(ctx, 3, 7)).toBe(false)
    })

    it('uses configured school hours for generated timetable result times when slot times are missing', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            schoolHours: [
                {
                    hour: 5,
                    from: '11:10',
                    until: '11:55',
                },
            ],
            generatedTimetable: {
                slots: {
                    '1-5': {
                        key: 'slot-1',
                        code: 'M6',
                        courseGroup: {
                            hour: 5,
                        },
                    },
                },
            },
            generatedTimetableSchoolHour: methods.generatedTimetableSchoolHour,
            generatedTimetableConfiguredSchoolHour: methods.generatedTimetableConfiguredSchoolHour,
            generatedTimetableHourTimeFrom: methods.generatedTimetableHourTimeFrom,
            generatedTimetableHourTimeUntil: methods.generatedTimetableHourTimeUntil,
            formatTimeValue: methods.formatTimeValue,
        }

        expect(computed.generatedTimetableHours.call(ctx)).toEqual([
            {
                value: 5,
                hourLabel: '5.',
                timeFrom: '11:10',
                timeUntil: '11:55',
            },
        ])
    })
})
